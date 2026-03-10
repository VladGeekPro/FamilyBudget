@php
    $fmt = fn(float $v): string => number_format($v, 2, ',', ' ') . ' MDL';
@endphp

<x-filament-widgets::widget class="fi-wi-table">
    <div class="rounded-2xl overflow-hidden shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">

        {{-- ═══════════ HEADER ═══════════ --}}
        <div class="px-6 py-4 bg-gradient-to-br from-slate-600 via-slate-700 to-gray-800">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                {{-- Icon: always first --}}
                <div class="flex-shrink-0 order-1">
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-lg">
                        <x-heroicon-o-table-cells class="w-6 h-6 text-white" />
                    </div>
                </div>

                {{-- Title & Subtitle: on mobile goes to new line, on desktop between icon and actions --}}
                <div class="w-full sm:w-auto sm:flex-1 order-3 sm:order-2">
                    <h2 class="text-white font-bold text-xl leading-tight">{{ __('resources.widgets.expenses_table.title') }}</h2>
                    <p class="text-gray-300 text-sm mt-1">{{ __('resources.widgets.expenses_table.subtitle') }}</p>
                </div>

                {{-- Summary Stat: stay with icon on first line when wrapped --}}
                <div class="bg-white/10 backdrop-blur-md rounded-xl px-4 py-2 border border-white/15 shadow-md flex-shrink-0 order-2 sm:order-3 ml-auto sm:ml-0 min-w-[220px]">
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
            </div>
        </div>

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
        <div>
            {{ $this->table ?? null }}
        </div>
    </div>
</x-filament-widgets::widget>
