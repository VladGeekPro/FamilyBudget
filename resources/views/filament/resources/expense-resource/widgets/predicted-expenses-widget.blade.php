@php
    $userId = (int) ($currentUserId ?? 0);
@endphp

<x-filament-widgets::widget>
    <div
        x-data="window.predictedExpensesWidget({
            predictUrl: @js($predictUrl),
            storeUrl: @js($storeUrl),
            userId: @js($userId),
            suppliersMap: @js($suppliersMap ?? []),
            usersMap: @js($usersMap ?? []),
        })"
        class="space-y-6"
    >
        {{-- Контейнер 1: заголовок + описание + кнопка --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Прогнозные затраты</h2>
            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center">
                <p class="flex-1 text-sm text-gray-500 dark:text-gray-400">
                    Рекомендуемые затраты на основе истории. Если дневного прогноза нет, он будет рассчитан автоматически.
                </p>
                <x-filament::button
                    type="button"
                    x-on:click="predict()"
                    x-bind:disabled="isLoading"
                    color="primary"
                    class="w-full sm:w-auto"
                >
                    <span x-show="!isLoading">Спрогнозировать затраты</span>
                    <span x-show="isLoading">Прогнозируем...</span>
                </x-filament::button>
            </div>
        </div>

        {{-- Контейнер 2: карточки прогнозов (показывается только при наличии данных) --}}
        <div
            x-show="predictions.length > 0"
            x-cloak
            class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Желаете добавить затрату?</h2>
                <div class="hidden sm:flex">
                    <x-filament::button
                        type="button"
                        x-on:click="isCatalogOpen = true"
                        color="primary"
                    >
                        Показать весь список
                    </x-filament::button>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
                <template x-for="item in visiblePredictions()" :key="item.key">
                    <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                        <div class="p-4">
                            {{-- Верхняя строка: дата + аватар пользователя --}}
                            <div class="grid grid-cols-2 items-center">
                                <span class="fi-color fi-color-info fi-text-color-600 dark:fi-text-color-400 fi-size-sm fi-ta-text-item fi-ta-text fi-inline" x-text="date(item.date)"></span>
                                <div class="flex justify-end">
                                    <img
                                        x-bind:src="item.user_image_url"
                                        x-bind:alt="`user-${item.user_id}`"
                                        class="rounded-full object-cover"
                                        style="height: 40px; width: 40px;"
                                        x-show="item.user_image_url"
                                    >
                                </div>
                            </div>

                            {{-- Основной блок: аватар поставщика + данные --}}
                            <div class="mt-3 flex items-start gap-3 py-2">
                                <template x-if="item.supplier_image_url">
                                    <img
                                        x-bind:src="item.supplier_image_url"
                                        x-bind:alt="item.supplier_name"
                                        class="h-20 w-20 shrink-0 rounded-full object-cover"
                                    >
                                </template>
                                <template x-if="!item.supplier_image_url">
                                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-primary-100 text-2xl font-bold text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                                        <span x-text="(item.supplier_name || '#').charAt(0).toUpperCase()"></span>
                                    </div>
                                </template>

                                <div class="min-w-0 flex-1">
                                    <div class="grid grid-cols-3 items-baseline gap-1">
                                        <span class="col-span-2 fi-size-md fi-font-bold fi-ta-text-item fi-ta-text fi-inline truncate" x-text="item.supplier_name || `Поставщик #${item.supplier_id}`"></span>
                                        <span class="col-span-1 fi-color fi-color-warning fi-text-color-700 dark:fi-text-color-600 fi-size-sm fi-ta-text-item fi-ta-text fi-inline text-end" x-text="money(item.sum)"></span>
                                    </div>
                                    <span class="fi-size-xs fi-ta-text-item fi-ta-text fi-inline" x-text="item.category_name"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Подвал карточки: Скрыть (левый угол, красная) + Добавить (правый угол, зелёная) --}}
                        <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-danger-600 transition hover:text-danger-800 dark:text-danger-400 dark:hover:text-danger-300 disabled:opacity-50"
                                x-on:click="hidePrediction(item.key)"
                            >
                                <x-filament::icon icon="heroicon-o-eye-slash" class="h-4 w-4" />
                                Скрыть
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-success-600 transition hover:text-success-800 dark:text-success-400 dark:hover:text-success-300 disabled:opacity-50"
                                x-on:click="createExpense(item)"
                                x-bind:disabled="isCreating || createdKeys.includes(item.key)"
                            >
                                <x-filament::icon icon="heroicon-o-plus-circle" class="h-4 w-4" />
                                <span x-show="!createdKeys.includes(item.key)">Добавить</span>
                                <span x-show="createdKeys.includes(item.key)">Добавлено</span>
                            </button>
                        </div>
                    </article>
                </template>
            </div>

            {{-- Кнопка «Показать весь список» на мобиле -- полная ширина в низу --}}
            <div class="mt-4 sm:hidden">
                <x-filament::button
                    type="button"
                    x-on:click="isCatalogOpen = true"
                    color="primary"
                    class="w-full"
                >
                    Показать весь список
                </x-filament::button>
            </div>

            {{-- Пустое состояние когда все карточки топ-3 скрыты --}}
            <div
                x-show="predictions.length > 0 && visiblePredictions().length === 0"
                class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400"
            >
                Все карточки из топ-3 скрыты. Откройте весь список и выберите нужного поставщика.
            </div>
        </div>

        {{-- Модальное окно: полный список прогнозов --}}
        <div
            x-show="isCatalogOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"
        >
            <div class="max-h-[85vh] w-full max-w-7xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Все прогнозные затраты</h2>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300"
                        x-on:click="isCatalogOpen = false"
                        aria-label="Закрыть"
                    >
                        <span class="text-lg leading-none">×</span>
                    </button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto p-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <template x-for="item in predictions" :key="`catalog-${item.key}`">
                            <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950">
                                <div class="p-4">
                                    <div class="grid grid-cols-2 items-center">
                                        <span class="fi-color fi-color-info fi-text-color-600 dark:fi-text-color-400 fi-size-sm fi-ta-text-item fi-ta-text fi-inline" x-text="date(item.date)"></span>
                                        <div class="flex justify-end">
                                            <img
                                                x-bind:src="item.user_image_url"
                                                x-bind:alt="`user-${item.user_id}`"
                                                class="rounded-full object-cover"
                                                style="height: 40px; width: 40px;"
                                                x-show="item.user_image_url"
                                            >
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-start gap-3 py-2">
                                        <template x-if="item.supplier_image_url">
                                            <img
                                                x-bind:src="item.supplier_image_url"
                                                x-bind:alt="item.supplier_name"
                                                class="h-20 w-20 shrink-0 rounded-full object-cover"
                                            >
                                        </template>
                                        <template x-if="!item.supplier_image_url">
                                            <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-primary-100 text-2xl font-bold text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                                                <span x-text="(item.supplier_name || '#').charAt(0).toUpperCase()"></span>
                                            </div>
                                        </template>

                                        <div class="min-w-0 flex-1">
                                            <div class="grid grid-cols-3 items-baseline gap-1">
                                                <span class="col-span-2 fi-size-md fi-font-bold fi-ta-text-item fi-ta-text fi-inline truncate" x-text="item.supplier_name || `Поставщик #${item.supplier_id}`"></span>
                                                <span class="col-span-1 fi-color fi-color-warning fi-text-color-700 dark:fi-text-color-600 fi-size-sm fi-ta-text-item fi-ta-text fi-inline text-end" x-text="money(item.sum)"></span>
                                            </div>
                                            <span class="fi-size-xs fi-ta-text-item fi-ta-text fi-inline" x-text="item.category_name"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 text-sm font-medium text-success-600 transition hover:text-success-800 dark:text-success-400 dark:hover:text-success-300 disabled:opacity-50"
                                        x-on:click="createExpense(item)"
                                        x-bind:disabled="isCreating || createdKeys.includes(item.key)"
                                    >
                                        <x-filament::icon icon="heroicon-o-plus-circle" class="h-4 w-4" />
                                        <span x-show="!createdKeys.includes(item.key)">Добавить</span>
                                        <span x-show="createdKeys.includes(item.key)">Добавлено</span>
                                    </button>
                                </div>
                            </article>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
