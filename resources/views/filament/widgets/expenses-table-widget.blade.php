@php
    use App\Models\User;

    $fmt = fn(float $v): string => number_format($v, 2, ',', ' ') . ' MDL';

    $av = function(object $u, string $size = 'h-8 w-8'): string {
        if ($u->image) {
            return '<img src="' . asset('storage/' . e($u->image)) . '"
                         alt="' . e($u->name) . '"
                         class="' . $size . ' rounded-full object-cover ring-2 ring-white/60 shadow" />';
        }
        $icon = e(User::getIcon($u->email));
        return '<span class="inline-flex ' . $size . ' items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-lg shadow">' . $icon . '</span>';
    };
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
                    <h2 class="text-white font-bold text-lg leading-tight">Журнал расходов</h2>
                    <p class="text-gray-300 text-sm">Все транзакции • группировка по месяцам</p>
                </div>
            </div>

            {{-- Summary pill --}}
            <div class="flex-shrink-0 flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2">
                <div class="text-right">
                    <div class="text-white text-xs font-medium">Итого по фильтру</div>
                    <div class="text-gray-300 text-xs">{{ $totalCount }} транзакций</div>
                </div>
                <div class="text-white font-extrabold text-lg">{{ $fmt($totalExpenses) }}</div>
            </div>
        </div>

        {{-- ═══════════ STATS ROW ═══════════ --}}
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

                {{-- Per-user mini cards --}}
                @foreach($userBreakdown as $ub)
                    <div class="flex items-center gap-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2.5">
                        {!! $av($ub->user) !!}
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $ub->user->name }}</div>
                            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $fmt($ub->total) }}</div>
                            <div class="text-[10px] text-gray-400 dark:text-gray-500">{{ $ub->count }} тр.</div>
                        </div>
                    </div>
                @endforeach

                {{-- Avg per transaction --}}
                <div class="flex items-center gap-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2.5">
                    <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                        <x-heroicon-m-receipt-percent class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Ср. чек</div>
                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $fmt($avgPerTx) }}</div>
                    </div>
                </div>

                {{-- Avg per day --}}
                <div class="flex items-center gap-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2.5">
                    <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                        <x-heroicon-m-clock class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500 dark:text-gray-400">В день</div>
                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $fmt($avgPerDay) }}</div>
                    </div>
                </div>
            </div>

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
            {{ $this->table }}
        </div>
    </div>
</x-filament-widgets::widget>
