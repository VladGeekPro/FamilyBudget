@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();

    $maxHeight = $this->getMaxHeight();

    $gradient = $this->getHeaderGradient();
    $icon = $this->getHeaderIcon();
    $title = $this->getHeaderTitle();
    $pill = $this->getHeaderPill();
    $description = $this->getHeaderDescription();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <div
        @if ($isCollapsible)
            x-data="{ isCollapsed: false }"
        @endif
        class="rounded-2xl overflow-hidden shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 h-full flex flex-col"
    >
        {{-- ═══════════ GRADIENT HEADER ═══════════ --}}
        <div
            class="px-5 py-3.5 bg-gradient-to-r {{ $gradient }} flex items-center gap-3 flex-shrink-0"
            @if ($isCollapsible)
                x-on:click="isCollapsed = !isCollapsed"
                role="button"
            @endif
        >
            <div class="flex items-center gap-3 flex-1 min-w-0">
                @if ($icon)
                    <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm">
                        <x-dynamic-component :component="$icon" class="w-5 h-5 text-white" />
                    </div>
                @endif
                <div class="min-w-0">
                    <h2 class="text-white font-bold text-base sm:text-lg leading-tight truncate">{{ $title }}</h2>
                    @if ($description)
                        <p class="text-white/70 text-xs sm:text-sm leading-snug truncate mt-0.5">{!! $description !!}</p>
                    @endif
                </div>
            </div>

            @if ($pill)
                <div class="hidden sm:flex flex-shrink-0 items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-3.5 py-1.5">
                    <span class="text-white/90 text-xs sm:text-sm font-semibold whitespace-nowrap">{{ $pill }}</span>
                </div>
            @endif

            @if ($isCollapsible)
                <div class="flex-shrink-0 w-7 h-7 rounded-full bg-white/15 flex items-center justify-center">
                    <svg
                        class="w-4 h-4 text-white transition-transform duration-300"
                        :class="isCollapsed && 'rotate-180'"
                        fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                    </svg>
                </div>
            @endif
        </div>

        {{-- ═══════════ CHART CONTENT ═══════════ --}}
        <div
            @if ($isCollapsible)
                x-show="!isCollapsed"
                x-transition:enter="transition-all ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-all ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="overflow-hidden flex-1 flex flex-col"
            @else
                class="flex-1 flex flex-col"
            @endif
        >
            <div
                @if ($pollingInterval = $this->getPollingInterval())
                    wire:poll.{{ $pollingInterval }}="updateChartData"
                @endif
                class="p-3 flex-1 flex flex-col justify-center w-full"
            >
                <div
                    x-load
                    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                    wire:ignore
                    data-chart-type="{{ $type }}"
                    x-data="chart({
                        cachedData: @js($this->getCachedData()),
                        maxHeight: @js($maxHeight),
                        options: @js($this->getOptions()),
                        type: @js($type),
                    })"
                    {{
                        (new ComponentAttributeBag)
                            ->color(ChartWidgetComponent::class, $color)
                            ->class([
                                'fi-wi-chart-canvas-ctn w-full',
                                'fi-wi-chart-canvas-ctn-no-aspect-ratio' => true,
                            ])
                    }}
                >
                    <canvas
                        x-ref="canvas"
                        @if ($maxHeight)
                            style="max-height: {{ $maxHeight }}"
                        @endif
                    ></canvas>

                    <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                    <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                    <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                    <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
