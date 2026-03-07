@php
use App\Models\User;

$fmt = fn(float $v): string => number_format($v, 2, ',', ' ') . ' MDL';

$av = function(object $u, string $size = 'h-10 w-10', string $ring = 'ring-2 ring-white/60'): string {
if ($u->image) {
return '<img src="' . asset('storage/' . e($u->image)) . '"
    alt="' . e($u->name) . '"
    class="' . $size . ' rounded-full object-cover ' . $ring . ' shadow" />';
}
$icon = e(User::getIcon($u->email));
return '<span class="inline-flex ' . $size . ' items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-xl shadow">' . $icon . '</span>';
};
@endphp

<x-filament-widgets::widget>
    <div class="rounded-2xl overflow-hidden shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">

        {{-- ═══════════ HEADER ═══════════ --}}
        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm">
                    <x-heroicon-o-chart-bar class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h2 class="text-white font-bold text-lg leading-tight">Сравнение с прошлым месяцем</h2>
                    <p class="text-blue-200 text-sm">{{ $monthLabel }} vs {{ $prevMonthLabel }}</p>
                </div>
            </div>

            {{-- Month progress pill --}}
            <div class="flex-shrink-0 flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2">
                <div class="text-right">
                    <div class="text-white text-xs font-medium">Прогресс месяца</div>
                    <div class="text-blue-200 text-xs">{{ $daysElapsed }} / {{ $daysInMonth }} дн. • ещё {{ $daysRemaining }} дн.</div>
                </div>
                <div class="relative w-12 h-12 flex-shrink-0">
                    <svg class="w-12 h-12 -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15.5" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="3" />
                        <circle cx="18" cy="18" r="15.5" fill="none"
                            stroke="white" stroke-width="3"
                            stroke-dasharray="{{ round($monthProgress * 97.4 / 100, 1) }}, 97.4"
                            stroke-linecap="round" />
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-white text-[10px] font-bold">{{ $monthProgress }}%</span>
                </div>
            </div>
        </div>

        {{-- ═══════════ MAIN CONTENT ═══════════ --}}
        <div class="px-6 py-5 space-y-5">

            {{-- ── MONTH TOTALS COMPARISON ── --}}
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_auto_1fr] gap-4 items-stretch">

                {{-- Previous month card --}}
                <div class="rounded-xl border-2 border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 p-5 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gray-400 dark:bg-gray-600 flex items-center justify-center">
                                <x-heroicon-m-clock class="w-4 h-4 text-white" />
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold">Прошлый месяц</div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">{{ $prevMonthLabel }}</div>
                            </div>
                        </div>
                        <span class="text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-full px-2 py-0.5">
                            завершён
                        </span>
                    </div>

                    <div class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $fmt($previousTotal) }}</div>

                    {{-- Mini daily sparkline --}}
                    @if(count($dailyPrevious) > 1)
                    <div class="h-12 flex items-end gap-px">
                        @php $maxDailyP = max(1, max($dailyPrevious)); @endphp
                        @foreach($dailyPrevious as $dv)
                        <div class="flex-1 bg-gray-300/70 dark:bg-gray-600/60 rounded-t-sm transition-all"
                            style="height: {{ max(2, round($dv / $maxDailyP * 100)) }}%"
                            title="{{ $fmt($dv) }}"></div>
                        @endforeach
                    </div>
                    <div class="flex justify-between text-[10px] text-gray-400 dark:text-gray-500 -mt-1">
                        <span>1</span>
                        <span>{{ count($dailyPrevious) }}</span>
                    </div>
                    @endif

                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700 pt-2">
                        <x-heroicon-m-clock class="w-3.5 h-3.5" />
                        <span>В среднем <strong class="text-gray-700 dark:text-gray-200">{{ $fmt($avgPerDayPrev) }}</strong> /день</span>
                    </div>
                </div>

                {{-- Delta indicator (center) --}}
                <div class="hidden lg:flex flex-col items-center justify-center gap-2 px-2">
                    @if(abs($deltaPercent) < 0.1)
                        <div class="w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center shadow-md">
                        <x-heroicon-m-equals class="w-8 h-8 text-gray-400" />
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 text-center">Без<br>изменений</span>
                @else
                <div class="text-3xl font-extrabold {{ $delta <= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $delta > 0 ? '+' : '' }}{{ $deltaPercent }}%
                </div>
                <div class="w-14 h-14 rounded-full {{ $delta <= 0 ? 'bg-gradient-to-br from-green-500 to-emerald-500' : 'bg-gradient-to-br from-red-500 to-orange-500' }} shadow-lg flex items-center justify-center">
                    @if($delta
                    <= 0)
                        <x-heroicon-m-arrow-trending-down class="w-7 h-7 text-white" />
                    @else
                    <x-heroicon-m-arrow-trending-up class="w-7 h-7 text-white" />
                    @endif
                </div>
                <div class="text-center">
                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $fmt($deltaAbs) }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $delta <= 0 ? 'экономия' : 'перерасход' }}</div>
                </div>
                @endif
            </div>

            {{-- Current month card --}}
            <div class="rounded-xl border-2 border-blue-200 dark:border-blue-700/50 bg-blue-50/60 dark:bg-blue-900/10 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center">
                            <x-heroicon-m-calendar-days class="w-4 h-4 text-white" />
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold">Текущий месяц</div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">{{ $monthLabel }}</div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold bg-blue-100 dark:bg-blue-800/40 text-blue-600 dark:text-blue-400 rounded-full px-2 py-0.5">
                        {{ $daysElapsed }} / {{ $daysInMonth }} дн.
                    </span>
                </div>

                <div class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $fmt($currentTotal) }}</div>

                {{-- Mini daily sparkline --}}
                @if(count($dailyCurrent) > 1)
                <div class="h-12 flex items-end gap-px">
                    @php $maxDaily = max(1, max($dailyCurrent)); @endphp
                    @foreach($dailyCurrent as $dv)
                    <div class="flex-1 bg-blue-400/70 dark:bg-blue-500/60 rounded-t-sm transition-all"
                        style="height: {{ max(2, round($dv / $maxDaily * 100)) }}%"
                        title="{{ $fmt($dv) }}"></div>
                    @endforeach
                </div>
                <div class="flex justify-between text-[10px] text-gray-400 dark:text-gray-500 -mt-1">
                    <span>1</span>
                    <span>{{ count($dailyCurrent) }}</span>
                </div>
                @endif

                <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 border-t border-blue-100 dark:border-blue-800/40 pt-2">
                    <x-heroicon-m-clock class="w-3.5 h-3.5" />
                    <span>В среднем <strong class="text-gray-700 dark:text-gray-200">{{ $fmt($avgPerDay) }}</strong> /день</span>
                </div>
            </div>
        </div>

        {{-- Mobile delta --}}
        <div class="lg:hidden rounded-xl {{ $delta <= 0 ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700' }} border p-4 text-center">
            <div class="text-2xl font-extrabold {{ $delta <= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $delta > 0 ? '+' : '' }}{{ $deltaPercent }}%
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $delta <= 0 ? 'Экономия' : 'Перерасход' }}: {{ $fmt($deltaAbs) }}</div>
        </div>

        {{-- ── CUMULATIVE CHART ── --}}
        @if(count($cumulativeCurrent) > 1 || count($cumulativePrevious) > 1)
        @php
        $maxCum = max(1, max(
        count($cumulativeCurrent) ? max($cumulativeCurrent) : 0,
        count($cumulativePrevious) ? max($cumulativePrevious) : 0
        ));
        $maxPoints = max(count($cumulativeCurrent), count($cumulativePrevious));
        $svgW = 600;
        $svgH = 120;
        $padX = 0;
        $padY = 5;

        $toPath = function(array $data, float $maxVal) use ($svgW, $svgH, $maxPoints, $padX, $padY): string {
        if (count($data) < 2) return '' ;
            $points=[];
            foreach ($data as $i=> $v) {
            $x = $padX + ($maxPoints > 1 ? ($i / ($maxPoints - 1)) * ($svgW - 2 * $padX) : 0);
            $y = $padY + ($svgH - 2 * $padY) - ($maxVal > 0 ? ($v / $maxVal) * ($svgH - 2 * $padY) : 0);
            $points[] = round($x, 1) . ',' . round($y, 1);
            }
            return 'M' . implode(' L', $points);
            };

            $currentPath = $toPath($cumulativeCurrent, $maxCum);
            $previousPath = $toPath($cumulativePrevious, $maxCum);

            // Fill path for current
            $toFill = function(array $data, float $maxVal) use ($svgW, $svgH, $maxPoints, $padX, $padY): string {
            if (count($data) < 2) return '' ;
                $points=[];
                foreach ($data as $i=> $v) {
                $x = $padX + ($maxPoints > 1 ? ($i / ($maxPoints - 1)) * ($svgW - 2 * $padX) : 0);
                $y = $padY + ($svgH - 2 * $padY) - ($maxVal > 0 ? ($v / $maxVal) * ($svgH - 2 * $padY) : 0);
                $points[] = round($x, 1) . ',' . round($y, 1);
                }
                $lastX = $padX + ((count($data) - 1) / ($maxPoints - 1)) * ($svgW - 2 * $padX);
                $points[] = round($lastX, 1) . ',' . ($svgH - $padY);
                $points[] = $padX . ',' . ($svgH - $padY);
                return 'M' . implode(' L', $points) . ' Z';
                };
                $currentFill = $toFill($cumulativeCurrent, $maxCum);
                @endphp
                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide flex items-center gap-1.5">
                            <x-heroicon-m-chart-bar class="w-3.5 h-3.5" />
                            Накопительный расход
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="flex items-center gap-1">
                                <span class="w-3 h-0.5 bg-blue-500 rounded-full inline-block"></span>
                                <span class="text-gray-500 dark:text-gray-400 capitalize">{{ $monthLabel }}</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="w-3 h-0.5 bg-gray-400 rounded-full inline-block" style="border-bottom: 1px dashed"></span>
                                <span class="text-gray-500 dark:text-gray-400 capitalize">{{ $prevMonthLabel }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="relative">
                        <svg viewBox="0 0 {{ $svgW }} {{ $svgH }}" class="w-full h-28" preserveAspectRatio="none">
                            {{-- Grid lines --}}
                            @for($i = 0; $i
                            <= 4; $i++)
                                <line x1="0" y1="{{ $padY + ($svgH - 2 * $padY) * $i / 4 }}"
                                x2="{{ $svgW }}" y2="{{ $padY + ($svgH - 2 * $padY) * $i / 4 }}"
                                stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-width="0.5" />
                            @endfor

                            {{-- Current month fill --}}
                            @if($currentFill)
                            <path d="{{ $currentFill }}" fill="url(#blueFill)" opacity="0.3" />
                            @endif

                            {{-- Previous month line (dashed) --}}
                            @if($previousPath)
                            <path d="{{ $previousPath }}" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-dasharray="6,4" />
                            @endif

                            {{-- Current month line --}}
                            @if($currentPath)
                            <path d="{{ $currentPath }}" fill="none" stroke="#3B82F6" stroke-width="2.5" stroke-linecap="round" />
                            @endif

                            <defs>
                                <linearGradient id="blueFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#3B82F6" />
                                    <stop offset="100%" stop-color="#3B82F6" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                        </svg>

                        {{-- Y-axis labels --}}
                        <div class="absolute top-0 left-1 bottom-0 flex flex-col justify-between text-[9px] text-gray-400 dark:text-gray-500 pointer-events-none py-1">
                            <span>{{ number_format($maxCum, 0, ',', ' ') }}</span>
                            <span>{{ number_format($maxCum / 2, 0, ',', ' ') }}</span>
                            <span>0</span>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ── PER-USER BREAKDOWN ── --}}
                @if($userBreakdown->count() > 0)
                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 p-4">
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                        <x-heroicon-m-users class="w-3.5 h-3.5" />
                        Расходы по пользователям
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($userBreakdown as $ub)
                        @php
                        $userDeltaColor = $ub->delta <= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' ;
                            $userDeltaBg=$ub->delta <= 0 ? 'bg-green-100 dark:bg-green-800/40' : 'bg-red-100 dark:bg-red-800/40' ;
                                $userMaxBar=max($ub->current, $ub->previous, 1);
                                @endphp
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-2">
                                            {!! $av($ub->user) !!}
                                            <div class="font-semibold text-gray-900 dark:text-white text-sm">{{ $ub->user->name }}</div>
                                        </div>
                                        <span class="text-xs font-bold {{ $userDeltaColor }} {{ $userDeltaBg }} rounded-full px-2 py-0.5">
                                            {{ $ub->delta > 0 ? '+' : '' }}{{ $ub->deltaPercent }}%
                                        </span>
                                    </div>

                                    {{-- Current --}}
                                    <div class="mb-2">
                                        <div class="flex justify-between text-xs mb-0.5">
                                            <span class="text-gray-500 dark:text-gray-400 capitalize">{{ $monthLabel }}</span>
                                            <span class="font-bold text-gray-800 dark:text-gray-200">{{ $fmt($ub->current) }}</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="h-2 rounded-full bg-blue-500 transition-all duration-700"
                                                style="width: {{ round($ub->current / $userMaxBar * 100) }}%"></div>
                                        </div>
                                    </div>

                                    {{-- Previous --}}
                                    <div>
                                        <div class="flex justify-between text-xs mb-0.5">
                                            <span class="text-gray-500 dark:text-gray-400 capitalize">{{ $prevMonthLabel }}</span>
                                            <span class="font-bold text-gray-800 dark:text-gray-200">{{ $fmt($ub->previous) }}</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="h-2 rounded-full bg-gray-400 dark:bg-gray-500 transition-all duration-700"
                                                style="width: {{ round($ub->previous / $userMaxBar * 100) }}%"></div>
                                        </div>
                                    </div>

                                    {{-- Delta value --}}
                                    <div class="flex items-center gap-1 mt-2 text-xs {{ $userDeltaColor }}">
                                        @if($ub->delta
                                        <= 0)
                                            <x-heroicon-m-arrow-trending-down class="w-3.5 h-3.5" />
                                        @else
                                        <x-heroicon-m-arrow-trending-up class="w-3.5 h-3.5" />
                                        @endif
                                        <span>{{ $ub->delta <= 0 ? 'Меньше' : 'Больше' }} на {{ $fmt(abs($ub->delta)) }}</span>
                                    </div>
                                </div>
                                @endforeach
                    </div>
                </div>
                @endif

                {{-- ── CATEGORY COMPARISON ── --}}
                @if($categoryComparison->count() > 0)
                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 p-4">
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                        <x-heroicon-m-tag class="w-3.5 h-3.5" />
                        Топ категорий — сравнение
                    </div>

                    <div class="space-y-3">
                        @foreach($categoryComparison as $cat)
                        @php
                        $catDelta = $cat->delta;
                        $catDeltaColor = $catDelta <= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' ;
                            @endphp
                            <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $cat->name }}</span>
                                <span class="text-xs font-semibold {{ $catDeltaColor }}">
                                    @if($catDelta != 0)
                                    {{ $catDelta > 0 ? '+' : '' }}{{ $fmt($catDelta) }}
                                    @else
                                    —
                                    @endif
                                </span>
                            </div>
                            <div class="flex gap-1 items-center">
                                {{-- Current --}}
                                <div class="flex-1">
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 relative overflow-hidden">
                                        <div class="h-3 rounded-full bg-blue-500 transition-all duration-700 flex items-center justify-end pr-1"
                                            style="width: {{ max(2, round($cat->current / $maxCategoryTotal * 100)) }}%">
                                            @if($cat->current / $maxCategoryTotal > 0.2)
                                            <span class="text-[9px] text-white font-bold">{{ number_format($cat->current, 0, ',', ' ') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                {{-- Previous --}}
                                <div class="flex-1">
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 relative overflow-hidden">
                                        <div class="h-3 rounded-full bg-gray-400 dark:bg-gray-500 transition-all duration-700 flex items-center justify-end pr-1"
                                            style="width: {{ max(2, round($cat->previous / $maxCategoryTotal * 100)) }}%">
                                            @if($cat->previous / $maxCategoryTotal > 0.2)
                                            <span class="text-[9px] text-white font-bold">{{ number_format($cat->previous, 0, ',', ' ') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-1 text-[9px] text-gray-400 dark:text-gray-500 mt-0.5">
                                <div class="flex-1 capitalize">{{ $monthLabel }}</div>
                                <div class="flex-1 capitalize">{{ $prevMonthLabel }}</div>
                            </div>
                    </div>
                    @endforeach
                </div>
    </div>
    @endif

    {{-- ── STATS ROW: Forecast + Best/Worst day ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        {{-- Forecast --}}
        <div class="rounded-xl border {{ $forecastDelta > 0 ? 'border-amber-200 dark:border-amber-700/50 bg-amber-50/60 dark:bg-amber-900/10' : 'border-green-200 dark:border-green-700/50 bg-green-50/60 dark:bg-green-900/10' }} p-4">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg {{ $forecastDelta > 0 ? 'bg-amber-500' : 'bg-green-500' }} flex items-center justify-center">
                    <x-heroicon-m-arrow-trending-up class="w-4 h-4 text-white" />
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wide">Прогноз</div>
            </div>
            <div class="text-xl font-extrabold text-gray-900 dark:text-white">{{ $fmt($forecast) }}</div>
            <div class="text-xs mt-1 {{ $forecastDelta > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                @if($previousTotal > 0)
                {{ $forecastDelta > 0 ? '+' : '' }}{{ $forecastDelta }}% от прошлого месяца
                @else
                на основе {{ $fmt($avgPerDay) }}/день
                @endif
            </div>
            <div class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                {{ $fmt($avgPerDay) }} × {{ $daysInMonth }} дн.
            </div>
        </div>

        {{-- Best day --}}
        @if($bestDay)
        <div class="rounded-xl border border-green-200 dark:border-green-700/50 bg-green-50/60 dark:bg-green-900/10 p-4">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-green-500 flex items-center justify-center">
                    <x-heroicon-m-arrow-down class="w-4 h-4 text-white" />
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wide">Мин. расход</div>
            </div>
            <div class="text-xl font-extrabold text-green-700 dark:text-green-400">{{ $fmt($bestDayAmount) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ $bestDay->translatedFormat('j F (l)') }}
            </div>
        </div>
        @endif

        {{-- Worst day --}}
        @if($worstDay)
        <div class="rounded-xl border border-red-200 dark:border-red-700/50 bg-red-50/60 dark:bg-red-900/10 p-4">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 rounded-lg bg-red-500 flex items-center justify-center">
                    <x-heroicon-m-arrow-up class="w-4 h-4 text-white" />
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wide">Макс. расход</div>
            </div>
            <div class="text-xl font-extrabold text-red-700 dark:text-red-400">{{ $fmt($worstDayAmount) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ $worstDay->translatedFormat('j F (l)') }}
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════ RESULT BANNER ═══════════ --}}
    <div class="rounded-xl {{ $delta <= 0 ? 'bg-gradient-to-r from-green-500 to-emerald-600' : 'bg-gradient-to-r from-red-500 to-rose-600' }} p-4 text-white flex items-center gap-4">
        @if($delta <= 0)
            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-white/20 flex items-center justify-center">
            <x-heroicon-m-arrow-trending-down class="w-7 h-7" />
    </div>
    <div class="flex-1 min-w-0">
        <div class="font-bold text-lg leading-tight">Расходы снизились!</div>
        <div class="text-green-100 text-sm">{{ $monthLabel }}: экономия {{ $fmt($deltaAbs) }} ({{ abs($deltaPercent) }}%) по сравнению с {{ $prevMonthLabel }}</div>
    </div>
    @else
    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-white/20 flex items-center justify-center">
        <x-heroicon-m-arrow-trending-up class="w-7 h-7" />
    </div>
    <div class="flex-1 min-w-0">
        <div class="font-bold text-lg leading-tight">Расходы выросли</div>
        <div class="text-red-100 text-sm">{{ $monthLabel }}: перерасход {{ $fmt($deltaAbs) }} (+{{ $deltaPercent }}%) по сравнению с {{ $prevMonthLabel }}</div>
    </div>
    <div class="flex-shrink-0 text-right">
        <div class="text-2xl font-extrabold">+{{ $deltaPercent }}%</div>
        <div class="text-red-200 text-xs">рост расходов</div>
    </div>
    @endif
    </div>

    </div>
    </div>
</x-filament-widgets::widget>