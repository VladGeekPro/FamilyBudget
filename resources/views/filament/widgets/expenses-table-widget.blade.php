@php
    $fmt = fn(float $v): string => number_format($v, 2, ',', ' ') . ' MDL';
@endphp

<x-filament-widgets::widget class="fi-wi-table">
    <div class="rounded-2xl overflow-hidden shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">

        {{-- ═══════════ HEADER ═══════════ --}}
        <div class="px-6 py-4 bg-gradient-to-r from-slate-600 via-gray-700 to-zinc-700 flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm">
                    <x-heroicon-o-table-cells class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h2 class="text-white font-bold text-lg leading-tight">{{ __('resources.widgets.expenses_table.title') }}</h2>
                    <p class="text-gray-300 text-sm">{{ __('resources.widgets.expenses_table.subtitle') }}</p>
                </div>
            </div>

            {{-- Summary pill --}}
            <div class="flex-shrink-0 flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2">
                <div class="text-right">
                    <div class="text-white text-xs font-medium">{{ __('resources.widgets.expenses_table.filtered_total') }}</div>
                    <div class="text-gray-300 text-xs">{{ $totalCount }} {{ trans_choice('resources.terms.expense_count', $totalCount) }}</div>
                </div>
                <div class="text-white font-extrabold text-lg">{{ $fmt($totalExpenses) }}</div>
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
