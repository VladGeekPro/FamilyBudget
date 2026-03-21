@php
    $fmt = fn(float $v): string => number_format($v, 2, ',', ' ') . ' MDL';
@endphp

<x-filament-widgets::widget class="fi-wi-table expenses-table-widget">

    <div x-data="{ isCollapsed: false }" class="rounded-2xl overflow-hidden shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">

        {{-- ═══════════ HEADER ═══════════ --}}
        <div class="px-6 py-4 bg-gradient-to-br from-slate-600 via-slate-700 to-gray-800">
            <div
                x-data="{ isRightWrapped: false, syncRightWrap() { const row = this.$refs.headerRow; const right = this.$refs.headerRight; if (!row || !right) return; this.isRightWrapped = false; this.$nextTick(() => { this.isRightWrapped = right.offsetTop > row.offsetTop + 1; }); } }"
                x-init="$nextTick(() => { syncRightWrap(); const observer = new ResizeObserver(() => syncRightWrap()); observer.observe($refs.headerRow); observer.observe($refs.headerRight); window.addEventListener('resize', syncRightWrap); })"
                x-ref="headerRow"
                class="flex flex-wrap gap-x-4 gap-y-3"
            >
                {{-- Icon: always first --}}
                <div class=" order-2 aspect-square min-h-[40px] rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-lg">
                    <x-heroicon-o-table-cells class="w-6 h-6 text-white" />
                </div>

                {{-- Title & Subtitle: on mobile goes to new line, on desktop between icon and actions --}}
                <div
                    :class="isRightWrapped ? 'order-3' : 'order-4 sm:order-3'"
                    class="sm:flex-1 self-center"
                >
                    <h2 class="text-white font-bold text-xl leading-tight">{{ __('resources.widgets.expenses_table.title') }}</h2>
                    <p class="text-gray-300 text-sm mt-1">{{ __('resources.widgets.expenses_table.subtitle') }}</p>
                </div>

                {{-- Summary Stat: stay with icon on first line when wrapped --}}
                <div
                    x-ref="headerRight"
                    :class="isRightWrapped ? 'order-1 ml-0 basis-full' : 'order-3 ml-auto sm:order-4'"
                    class="flex items-stretch rounded-xl border border-white/15 bg-white/10 backdrop-blur-md shadow-md  overflow-hidden"
                >
                    <div class="px-2 py-1 w-full">
                        <div class="text-center text-gray-200 text-[11px] font-semibold uppercase tracking-wider opacity-90">
                            {{ __('resources.widgets.expenses_table.filtered_total') }}
                        </div>

                        <div class="mt-1.5 flex items-center justify-center">
                            <div class="text-white text-sm font-bold px-3">
                                {{ $totalCount }} {{ trans_choice('resources.terms.expense_count', $totalCount) }}
                            </div>
                            <div class="h-5 w-px bg-white/25"></div>
                            <div class="text-white font-extrabold text-lg px-3 whitespace-nowrap">
                                {{ $fmt($totalExpenses) }}
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        x-on:click.stop="isCollapsed = !isCollapsed"
                        class="px-3 text-white border-l border-white/20 hover:bg-white/20 hover:border-white/30 transition-all duration-200"
                        aria-label="Свернуть виджет"
                    >
                        <svg class="w-5 h-5 transition-transform duration-300" :class="isCollapsed && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div
            x-show="!isCollapsed"
            x-transition:enter="transition-all ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-all ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="overflow-hidden"
        >

        {{-- ═══════════ STATS ROW ═══════════ --}}
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30">
            {{-- Top categories row --}}
            @if($topCategories->count() > 0)
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider font-semibold">Топ:</span>
                    @foreach($topCategories as $cat)
                        @php
                            $share = $totalExpenses > 0 ? round($cat->total / $totalExpenses * 100, 1) : 0;
                        @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-2.5 py-1 text-xs">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $cat->name }}</span>
                            <span class="text-gray-400 dark:text-gray-500">•</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $share }}%</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ═══════════ TABLE ═══════════ --}}
        
        <div class="max-h-[45rem] overflow-y-auto sm:max-h-none">
            {{ $this->table ?? null }}
        </div>
        </div>
    </div>
</x-filament-widgets::widget>
