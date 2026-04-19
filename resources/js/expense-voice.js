const now = () => Math.floor(Date.now() / 1000)

const createExpenseVoiceRecorder = (config) => {
    return {
        ...config,
        analyser: null,
        animationFrameId: null,
        audioBlob: null,
        audioChunks: [],
        audioContext: null,
        durationInSeconds: 0,
        isRecording: false,
        isSaving: false,
        lastPressedButton: null,
        pressPulseUntil: 0,
        mediaRecorder: null,
        previewText: config.idleLabel,
        recordedAt: null,
        startedAt: null,
        stream: null,
        supported: typeof window.MediaRecorder !== 'undefined' && !!navigator.mediaDevices?.getUserMedia,
        transcript: '',

        get formattedDuration() {
            const minutes = String(Math.floor(this.durationInSeconds / 60)).padStart(2, '0')
            const seconds = String(this.durationInSeconds % 60).padStart(2, '0')

            return `${minutes}:${seconds}`
        },

        get statusLabel() {
            if (this.isSaving) {
                return this.processingLabel
            }

            if (this.isRecording) {
                return this.recordingLabel
            }

            if (this.audioBlob) {
                return this.readyLabel
            }

            return this.idleLabel
        },

        buttonClass(name) {
            return {
                'expense-voice-btn-pressed': this.lastPressedButton === name && now() <= this.pressPulseUntil,
            }
        },

        pressButton(name) {
            this.lastPressedButton = name
            this.pressPulseUntil = now() + 1
        },

        handleCancelClick() {
            this.pressButton('cancel')
            this.closeModal()
        },

        handleRetryClick() {
            this.pressButton('retry')
            this.resetRecording()
        },

        handleSaveClick() {
            this.pressButton('save')
            this.saveRecording()
        },

        async toggleRecording() {
            if (!this.supported) {
                this.notify('danger', this.unsupportedTitle, this.unsupportedBody)
                return
            }

            if (this.isRecording) {
                await this.stopRecording()
                return
            }

            await this.startRecording()
        },

        async startRecording() {
            this.resetRecording(false)

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ audio: true })
                const mimeType = this.resolveMimeType()
                this.mediaRecorder = mimeType
                    ? new MediaRecorder(this.stream, { mimeType })
                    : new MediaRecorder(this.stream)
            } catch (error) {
                this.notify('danger', this.failedTitle, error?.message || this.unsupportedBody)
                this.cleanupAudioResources()
                return
            }

            this.audioChunks = []
            this.startedAt = now()
            this.durationInSeconds = 0
            this.isRecording = true
            this.previewText = this.recordingLabel
            this.transcript = ''

            this.mediaRecorder.addEventListener('dataavailable', (event) => {
                if (event.data?.size) {
                    this.audioChunks.push(event.data)
                }
            })

            this.mediaRecorder.addEventListener('stop', () => {
                const mimeType = this.mediaRecorder?.mimeType || 'audio/webm'
                this.audioBlob = new Blob(this.audioChunks, { type: mimeType })
                this.recordedAt = now()
                this.previewText = this.readyLabel
                this.cleanupAudioResources()
            }, { once: true })

            this.mediaRecorder.start(250)
            this.setupVisualizer()
            this.tickDuration()
        },

        async stopRecording() {
            if (!this.mediaRecorder || this.mediaRecorder.state === 'inactive') {
                return
            }

            await new Promise((resolve) => {
                this.mediaRecorder.addEventListener('stop', resolve, { once: true })
                this.mediaRecorder.stop()
                this.isRecording = false
            })
        },

        resetRecording(clearTranscript = true) {
            this.isRecording = false
            this.isSaving = false
            this.audioBlob = null
            this.audioChunks = []
            this.durationInSeconds = 0
            this.startedAt = null
            this.previewText = this.idleLabel

            if (clearTranscript) {
                this.transcript = ''
            }

            this.cleanupAudioResources()
            this.clearCanvas()
        },

        async saveRecording() {
            if (!this.audioBlob || this.isSaving) {
                return
            }

            this.isSaving = true

            try {
                const payload = new FormData()
                payload.append('mode', this.mode)
                payload.append('audio', this.audioBlob, this.buildFilename())

                const response = await fetch(this.transcriptionUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: payload,
                })

                const body = await response.json().catch(() => ({}))

                if (!response.ok) {
                    const error = new Error(body.message || this.failedTitle)
                    error.title = body.title || this.failedTitle
                    throw error
                }

                this.transcript = body.transcript || ''
                this.applyFieldUpdates(body.field_updates || {})
                this.notify('success', body.title || this.appliedTitle, body.body || this.appliedBody)
                this.closeModal()
            } catch (error) {
                this.notify('danger', error?.title || this.failedTitle, error?.message || this.failedTitle)
            } finally {
                this.isSaving = false
            }
        },

        applyFieldUpdates(fieldUpdates) {
            const componentId = this.$root.closest('[wire\\:id]')?.getAttribute('wire:id')
            const component = componentId ? window.Livewire.find(componentId) : null

            if (!component) {
                throw new Error('Livewire component not found for voice input.')
            }

            if (fieldUpdates.supplier_id) {
                component.set('data.supplier_id', fieldUpdates.supplier_id)
            }

            Object.entries(fieldUpdates).forEach(([field, value]) => {
                if (field === 'supplier_id') {
                    return
                }

                component.set(`data.${field}`, value)
            })
        },

        buildFilename() {
            return `${this.mode}-${Date.now()}.webm`
        },

        clearCanvas() {
            const canvas = this.$refs.canvas

            if (!canvas) {
                return
            }

            const context = canvas.getContext('2d')

            if (!context) {
                return
            }

            this.resizeCanvas()
            context.clearRect(0, 0, canvas.width, canvas.height)
        },

        cleanupAudioResources() {
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId)
                this.animationFrameId = null
            }

            this.stream?.getTracks()?.forEach((track) => track.stop())
            this.stream = null
            this.mediaRecorder = null
            this.analyser = null

            if (this.audioContext && this.audioContext.state !== 'closed') {
                this.audioContext.close()
            }

            this.audioContext = null
        },

        closeModal() {
            const modal = this.$root.closest('[data-fi-modal-id]')
            const modalId = modal?.dataset.fiModalId

            if (!modalId) {
                return
            }

            document.dispatchEvent(new CustomEvent('close-modal-quietly', {
                bubbles: true,
                composed: true,
                detail: { id: modalId },
            }))
        },

        handleModalClose(event) {
            const modal = this.$root.closest('[data-fi-modal-id]')
            const modalId = modal?.dataset.fiModalId

            if (!modalId || event.detail?.id !== modalId) {
                return
            }

            this.resetRecording(false)
        },

        notify(status, title, body = '') {
            if (!window.FilamentNotification) {
                return
            }

            const notification = new window.FilamentNotification().title(title)

            if (body) {
                notification.body(body)
            }

            notification[status]().send()
        },

        resolveMimeType() {
            const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg']

            return candidates.find((mimeType) => MediaRecorder.isTypeSupported(mimeType)) || null
        },

        resizeCanvas() {
            const canvas = this.$refs.canvas

            if (!canvas) {
                return
            }

            const width = canvas.clientWidth || 640
            const height = canvas.clientHeight || 140

            canvas.width = width
            canvas.height = height
        },

        setupVisualizer() {
            const canvas = this.$refs.canvas

            if (!canvas || !this.stream) {
                return
            }

            this.resizeCanvas()
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)()
            const source = this.audioContext.createMediaStreamSource(this.stream)
            this.analyser = this.audioContext.createAnalyser()
            this.analyser.fftSize = 256
            source.connect(this.analyser)

            const bufferLength = this.analyser.frequencyBinCount
            const data = new Uint8Array(bufferLength)
            const context = canvas.getContext('2d')

            const draw = () => {
                if (!this.analyser || !context) {
                    return
                }

                this.animationFrameId = requestAnimationFrame(draw)
                this.analyser.getByteFrequencyData(data)

                context.clearRect(0, 0, canvas.width, canvas.height)
                context.fillStyle = 'rgba(255, 247, 237, 0.9)'
                context.fillRect(0, 0, canvas.width, canvas.height)

                const barWidth = (canvas.width / bufferLength) * 1.7
                let x = 0

                for (let index = 0; index < bufferLength; index += 1) {
                    const barHeight = Math.max(data[index] * 0.55, 6)
                    const gradient = context.createLinearGradient(0, canvas.height, 0, canvas.height - barHeight)

                    gradient.addColorStop(0, '#f59e0b')
                    gradient.addColorStop(1, '#ea580c')

                    context.fillStyle = gradient
                    context.fillRect(x, canvas.height - barHeight, barWidth, barHeight)
                    x += barWidth + 2
                }
            }

            draw()
        },

        tickDuration() {
            if (!this.isRecording || !this.startedAt) {
                return
            }

            this.durationInSeconds = Math.max(now() - this.startedAt, 0)
            requestAnimationFrame(() => this.tickDuration())
        },
    }
}

window.expenseVoiceRecorder = createExpenseVoiceRecorder