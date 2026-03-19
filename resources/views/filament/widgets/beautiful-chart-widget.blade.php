@php
use Filament\Widgets\View\Components\ChartWidgetComponent;
use Illuminate\View\ComponentAttributeBag;

$color = $this->getColor();
$isCollapsible = $this->isCollapsible();
$type = $this->getType();

$maxHeight = $this->maxHeight;

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
        x-bind:class="isCollapsed ? 'self-start' : 'h-full'"
        @endif
        class="rounded-2xl overflow-hidden shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 flex flex-col {{ $isCollapsible ? '' : 'h-full' }}">
        {{-- ═══════════ GRADIENT HEADER ═══════════ --}}
        <div
            class="px-6 py-4 bg-gradient-to-br {{ $gradient }} flex-shrink-0"
            @if ($isCollapsible)
            x-on:click="isCollapsed = !isCollapsed"
            role="button"
            @endif>
            <div
                x-data="{ isRightWrapped: false, syncRightWrap() { const row = this.$refs.headerRow; const right = this.$refs.headerRight; if (!row || !right) return; this.isRightWrapped = false; this.$nextTick(() => { this.isRightWrapped = right.offsetTop > row.offsetTop + 1; }); } }"
                x-init="$nextTick(() => { syncRightWrap(); const observer = new ResizeObserver(() => syncRightWrap()); observer.observe($refs.headerRow); observer.observe($refs.headerRight); window.addEventListener('resize', syncRightWrap); })"
                x-ref="headerRow"
                class="flex flex-wrap gap-x-4 gap-y-3">
                {{-- Icon: always first --}}
                @if ($icon)
                <div class="order-2 flex-shrink-0 self-stretch">
                    <div class="h-full min-h-[40px] aspect-square rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-lg">
                        <x-dynamic-component :component="$icon" class="w-6 h-6 text-white" />
                    </div>
                </div>
                @endif

                {{-- Title & Description: on mobile goes to new line, on desktop between icon and actions --}}
                <div
                    :class="isRightWrapped ? 'order-3 sm:order-4' : 'order-4 sm:order-3'"
                    class="sm:flex-1 min-w-0 max-w-[340px] sm:max-w-none self-center">
                    <h2 class="text-white font-bold text-xl leading-tight truncate" title="{{ $title }}">{{ $title }}</h2>
                    @if ($description)
                    <p class="text-white/70 text-sm leading-snug mt-1 truncate" title="{{ strip_tags($description) }}">{!! $description !!}</p>
                    @endif
                </div>

                {{-- Right section: stay with icon on first line when wrapped --}}
                <div
                    x-ref="headerRight"
                    :class="isRightWrapped ? 'order-1 sm:order-3 sm:ml-auto' : 'order-3 ml-auto sm:order-4'"
                    class="flex items-stretch gap-3">
                    @if ($pill && $isCollapsible)
                    <div class="flex  w-full items-stretch rounded-xl border border-white/15 bg-white/10 backdrop-blur-md shadow-md overflow-hidden">
                        <div class=" w-full px-4 py-2 min-w-0 flex items-center">
                            <span class="w-full text-white font-semibold text-sm whitespace-nowrap " title="{{ $pill }}">{{ $pill }}</span>
                        </div>
                        <button
                            type="button"
                            x-on:click.stop="isCollapsed = !isCollapsed"
                            class="inline-flex items-center justify-center px-3 text-white border-l border-white/20 hover:bg-white/20 hover:border-white/30 transition-colors"
                            aria-label="Свернуть виджет">
                            <svg
                                class="w-5 h-5 transition-transform duration-300"
                                :class="isCollapsed && 'rotate-180'"
                                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                    </div>
                    @elseif ($pill)
                    <div class="flex items-center gap-2 bg-white/10 backdrop-blur-md rounded-xl px-4 py-2 border border-white/15 shadow-md max-w-[240px]">
                        <span class="text-white font-semibold text-sm whitespace-nowrap truncate" title="{{ $pill }}">{{ $pill }}</span>
                    </div>
                    @elseif ($isCollapsible)
                    <button
                        type="button"
                        x-on:click.stop="isCollapsed = !isCollapsed"
                        class="flex-shrink-0 w-10 h-10 rounded-lg bg-white/15 backdrop-blur-md flex items-center justify-center border border-white/20 hover:bg-white/20 transition-colors"
                        aria-label="Свернуть виджет">
                        <svg
                            class="w-5 h-5 text-white transition-transform duration-300"
                            :class="isCollapsed && 'rotate-180'"
                            fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    @endif
                </div>
            </div>
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
            @endif>
            <div
                @if ($pollingInterval=$this->getPollingInterval())
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
                                'fi-wi-chart-canvas-ctn-no-aspect-ratio' => filled($maxHeight),
                            ])
                    }}>
                    <canvas
                        x-ref="canvas"
                        @if ($maxHeight)
                        style="max-height: {{ $maxHeight }}"
                        @endif></canvas>

                    <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                    <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                    <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                    <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>