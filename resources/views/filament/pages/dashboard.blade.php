<x-filament-panels::page>
    @php
        $filterIndicators = $this->getActiveDashboardFilterIndicators();
    @endphp

    @if ($filterIndicators !== [])
        <div class="fi-ta-filter-indicators mb-4">
            <div class="flex items-center justify-between">
                <span class="fi-ta-filter-indicators-label">
                    Активные фильтры
                </span>

                <button type="button" x-tooltip="{
                    content: 'Очистить все фильтры',
                    theme: $store.theme,
                }" wire:click="removeDashboardAllFilters" wire:loading.attr="disabled" wire:target="removeDashboardAllFilters,removeDashboardFilter" class="fi-icon-btn fi-size-sm">
                    <svg class="fi-icon fi-size-sm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z"></path>
                    </svg>
                </button>
            </div>

            <div class="fi-ta-filter-indicators-badges-ctn">
                @foreach ($filterIndicators as $indicator)
                    <x-filament::badge>
                        {{ $indicator['label'] }}

                        <x-slot
                            name="deleteButton"
                            :label="__('filament-tables::table.filters.actions.remove.label')"
                            wire:click="removeDashboardFilter('{{ $indicator['key'] }}')"
                            wire:loading.attr="disabled"
                            wire:target="removeDashboardFilter"
                        ></x-slot>
                    </x-filament::badge>
                @endforeach
            </div>
        </div>
    @endif

    {{ $this->content }}
</x-filament-panels::page>
