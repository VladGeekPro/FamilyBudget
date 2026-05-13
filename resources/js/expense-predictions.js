const createPredictedExpensesWidget = (config) => {
    return {
        ...config,
        isLoading: false,
        isCreating: false,
        isCatalogOpen: false,
        predictions: [],
        createdKeys: [],

        async init() {
            const cached = this.readCachedPredictions()

            if (Array.isArray(cached) && cached.length) {
                this.predictions = cached
                return
            }

            await this.predict({ silent: true })
        },

        money(value) {
            const amount = Number(value || 0)

            return amount.toLocaleString('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }) + ' MDL'
        },

        date(value) {
            if (!value) {
                return '—'
            }

            const parsed = new Date(value)

            if (Number.isNaN(parsed.getTime())) {
                return String(value)
            }

            return parsed.toLocaleDateString('ru-RU')
        },

        notify(status, title, body = '') {
            
            // Полифилл для crypto.randomUUID
            if (!globalThis.crypto?.randomUUID) {
                if (!globalThis.crypto) globalThis.crypto = {}
                globalThis.crypto.randomUUID = () => {
                    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
                        const r = (Math.random() * 16) | 0
                        const v = c === 'x' ? r : (r & 0x3) | 0x8
                        return v.toString(16)
                    })
                }
            }
            
            if (!window.FilamentNotification) {
                this.showFallbackNotification(status, title, body)
                return
            }

            try {
                const notification = new window.FilamentNotification().title(title)

                if (body) {
                    notification.body(body)
                }

                notification[status]().send()
            } catch (error) {
                this.showFallbackNotification(status, title, body)
            }
        },

        showFallbackNotification(status, title, body = '') {
            const message = body ? `${title}\n${body}` : title
            if (status === 'danger' || status === 'error') {
                alert(`❌ ${message}`)
            } else if (status === 'success') {
                alert(`✅ ${message}`)
            } else {
                alert(`ℹ️ ${message}`)
            }
        },

        normalizePredictions(payload) {
            const candidates = [
                payload?.predictions,
                payload?.expenses,
                payload?.top_expenses,
                payload?.data,
                payload,
            ]

            const source = candidates.find((item) => Array.isArray(item)) || []

            return source
                .map((item, index) => {
                    const supplierId = Number(item?.supplier_id || 0)
                    const userId = Number(item?.user_id || this.userId)
                    const supplierInfo = this.suppliersMap?.[supplierId] || {}
                    const userInfo = this.usersMap?.[userId] || {}

                    return {
                        date: item?.date || new Date().toISOString().slice(0, 10),
                        sum: Number(item?.sum || 0),
                        supplier_id: supplierId,
                        user_id: userId,
                        category_name: item?.category_name || supplierInfo.category_name || '',
                        supplier_name: item?.supplier_name || supplierInfo.name || null,
                        supplier_image_url: supplierInfo.image_url || null,
                        user_image_url: userInfo.image_url || null,
                        show: item?.show !== false,
                        key: this.predictionKey(item, index),
                    }
                })
                .filter((item) => item.sum > 0 && item.supplier_id > 0 && item.user_id > 0)
        },

        predictionKey(item, index) {
            return [
                Number(item?.supplier_id || 0),
                item?.date || '',
                Number(item?.sum || 0),
                index,
            ].join(':')
        },

        visiblePredictions() {
            return this.predictions
                .filter((item) => item.show !== false)
                .slice(0, 3)
        },

        hidePrediction(key) {
            const target = this.predictions.find((item) => item.key === key)

            if (!target) {
                return
            }

            target.show = false
            this.writeCachedPredictions(this.predictions)
        },

        cacheKey() {
            const today = new Date().toISOString().slice(0, 10)

            return `expense-predictions:${this.userId}:${today}`
        },

        readCachedPredictions() {
            try {
                const raw = window.localStorage.getItem(this.cacheKey())

                if (!raw) {
                    return []
                }

                const parsed = JSON.parse(raw)

                return this.normalizePredictions(parsed)
            } catch {
                return []
            }
        },

        writeCachedPredictions(predictions) {
            try {
                window.localStorage.setItem(this.cacheKey(), JSON.stringify(predictions))
            } catch {
            }
        },

        async predict({ silent = false } = {}) {
            if (this.isLoading) {
                return
            }

            this.isLoading = true

            try {
                
                const response = await fetch(this.predictUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'Accept': 'application/json',
                    },
                })

                const payload = await response.json().catch(() => ({}))
                

                if (!response.ok) {
                    const errorMsg = payload?.message || payload?.errors ? JSON.stringify(payload) : `Ошибка ${response.status}: ${response.statusText}`
                    throw new Error(errorMsg)
                }

                const normalized = this.normalizePredictions(payload)

                if (!normalized.length) {
                    throw new Error('Модель не вернула подходящие затраты.')
                }

                this.predictions = normalized
                this.createdKeys = []
                this.writeCachedPredictions(normalized)

                if (!silent) {
                    this.notify('success', 'Прогноз готов', `Получены ${normalized.length} рекомендуемые затраты.`)
                }
            } catch (error) {
                this.notify('danger', 'Ошибка', error?.message || 'Ошибка при прогнозировании.')
            } finally {
                this.isLoading = false
            }
        },

        async createExpense(item) {
            if (this.isCreating || this.createdKeys.includes(item.key)) {
                return
            }

            this.isCreating = true

            try {
                const payload = {
                    date: item.date,
                    sum: item.sum,
                    supplier_id: item.supplier_id,
                    user_id: item.user_id,
                }
                
                
                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                })

                const responsePayload = await response.json().catch(() => ({}))
                

                if (!response.ok) {
                    const errorMsg = responsePayload?.message || responsePayload?.errors ? JSON.stringify(responsePayload) : `Ошибка ${response.status}: ${response.statusText}`
                    throw new Error(errorMsg)
                }

                this.createdKeys.push(item.key)
                this.hidePrediction(item.key)
                this.notify('success', 'Расход успешно создан')
                window.setTimeout(() => window.location.reload(), 450)
            } catch (error) {
                this.notify('danger', 'Ошибка', error?.message || 'Не удалось создать затрату.')
            } finally {
                this.isCreating = false
            }
        },
    }
}

window.predictedExpensesWidget = createPredictedExpensesWidget
