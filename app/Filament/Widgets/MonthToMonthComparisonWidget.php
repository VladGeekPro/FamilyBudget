<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithExpenseFilters;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class MonthToMonthComparisonWidget extends Widget
{
    use InteractsWithExpenseFilters;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.month-comparison-widget';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $today         = now();
        $currentStart  = now()->startOfMonth();
        $currentEnd    = now()->endOfDay();
        $previousStart = now()->subMonthNoOverflow()->startOfMonth();
        $previousEnd   = now()->subMonthNoOverflow()->endOfMonth();

        $daysInMonth   = (int) $today->daysInMonth;
        $daysElapsed   = (int) $today->day;
        $daysRemaining = $daysInMonth - $daysElapsed;
        $monthProgress = $daysInMonth > 0 ? round(($daysElapsed / $daysInMonth) * 100) : 0;

        $baseQuery = $this->expenseQuery(includeDateRange: false);

        // ── Totals ──
        $currentTotal = (float) (clone $baseQuery)
            ->whereDate('date', '>=', $currentStart->toDateString())
            ->whereDate('date', '<=', $currentEnd->toDateString())
            ->sum('sum');

        $previousTotal = (float) (clone $baseQuery)
            ->whereDate('date', '>=', $previousStart->toDateString())
            ->whereDate('date', '<=', $previousEnd->toDateString())
            ->sum('sum');

        // ── Delta ──
        $delta        = $currentTotal - $previousTotal;
        $deltaAbs     = abs($delta);
        $deltaPercent = $previousTotal > 0
            ? round(($delta / $previousTotal) * 100, 1)
            : ($currentTotal > 0 ? 100.0 : 0.0);

        // ── Per-day current month ──
        $dailyRaw = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('DATE(date) as d, COALESCE(SUM(sum),0) as t')
            ->whereDate('date', '>=', $currentStart->toDateString())
            ->whereDate('date', '<=', $currentEnd->toDateString())
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $dailyCurrent = [];
        $cursor = $currentStart->copy();
        while ($cursor->lte($currentEnd)) {
            $dailyCurrent[] = (float) ($dailyRaw->get($cursor->toDateString())?->t ?? 0);
            $cursor->addDay();
        }

        // ── Per-day previous month ──
        $dailyPrevRaw = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('DATE(date) as d, COALESCE(SUM(sum),0) as t')
            ->whereDate('date', '>=', $previousStart->toDateString())
            ->whereDate('date', '<=', $previousEnd->toDateString())
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $previousDaysInMonth = (int) $previousEnd->day;
        $dailyPrevious = [];
        $cursor = $previousStart->copy();
        while ($cursor->lte($previousEnd)) {
            $dailyPrevious[] = (float) ($dailyPrevRaw->get($cursor->toDateString())?->t ?? 0);
            $cursor->addDay();
        }

        // ── Cumulative ──
        $cumulativeCurrent  = [];
        $cumulativePrevious = [];
        $sum = 0;
        foreach ($dailyCurrent as $v) { $sum += $v; $cumulativeCurrent[] = round($sum, 2); }
        $sum = 0;
        foreach ($dailyPrevious as $v) { $sum += $v; $cumulativePrevious[] = round($sum, 2); }

        // ── Averages & Forecast ──
        $avgPerDay      = $daysElapsed > 0 ? $currentTotal / $daysElapsed : 0.0;
        $avgPerDayPrev  = $previousDaysInMonth > 0 ? $previousTotal / $previousDaysInMonth : 0.0;
        $forecast       = round($avgPerDay * $daysInMonth, 2);
        $forecastDelta  = $previousTotal > 0 ? round(($forecast - $previousTotal) / $previousTotal * 100, 1) : 0;

        // ── Per-user breakdown ──
        $allUsers = User::orderBy('name')->get();

        $currentByUser = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('user_id, COALESCE(SUM(sum),0) as total')
            ->whereDate('date', '>=', $currentStart->toDateString())
            ->whereDate('date', '<=', $currentEnd->toDateString())
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $previousByUser = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('user_id, COALESCE(SUM(sum),0) as total')
            ->whereDate('date', '>=', $previousStart->toDateString())
            ->whereDate('date', '<=', $previousEnd->toDateString())
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $userBreakdown = $allUsers->map(fn(User $u) => (object) [
            'user'         => $u,
            'current'      => (float) ($currentByUser[$u->id] ?? 0),
            'previous'     => (float) ($previousByUser[$u->id] ?? 0),
            'delta'        => (float) ($currentByUser[$u->id] ?? 0) - (float) ($previousByUser[$u->id] ?? 0),
            'deltaPercent' => ($previousByUser[$u->id] ?? 0) > 0
                ? round(((($currentByUser[$u->id] ?? 0) - ($previousByUser[$u->id] ?? 0)) / ($previousByUser[$u->id])) * 100, 1)
                : (($currentByUser[$u->id] ?? 0) > 0 ? 100.0 : 0.0),
        ])->values();

        // ── Top categories comparison ──
        $currentByCat = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('category_id, COALESCE(SUM(sum),0) as total')
            ->whereDate('date', '>=', $currentStart->toDateString())
            ->whereDate('date', '<=', $currentEnd->toDateString())
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $previousByCat = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('category_id, COALESCE(SUM(sum),0) as total')
            ->whereDate('date', '>=', $previousStart->toDateString())
            ->whereDate('date', '<=', $previousEnd->toDateString())
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $allCatIds = $currentByCat->keys()->merge($previousByCat->keys())->unique();
        $categories = Category::whereIn('id', $allCatIds)->pluck('name', 'id');

        $categoryComparison = $allCatIds->map(fn($catId) => (object) [
            'name'     => $categories[$catId] ?? 'Без категории',
            'current'  => (float) ($currentByCat[$catId] ?? 0),
            'previous' => (float) ($previousByCat[$catId] ?? 0),
            'delta'    => (float) ($currentByCat[$catId] ?? 0) - (float) ($previousByCat[$catId] ?? 0),
        ])->sortByDesc('current')->take(5)->values();

        $maxCategoryTotal = $categoryComparison->max(fn($c) => max($c->current, $c->previous)) ?: 1;

        // ── Best/worst day ──
        $bestDay  = null;
        $worstDay = null;
        if (count($dailyCurrent) > 0) {
            $minVal = INF;
            $maxVal = -INF;
            foreach ($dailyCurrent as $i => $v) {
                if ($v <= $minVal) { $minVal = $v; $bestDay = $currentStart->copy()->addDays($i); }
                if ($v >= $maxVal) { $maxVal = $v; $worstDay = $currentStart->copy()->addDays($i); }
            }
        }

        return [
            'currentStart'        => $currentStart,
            'currentEnd'          => $currentEnd,
            'previousStart'       => $previousStart,
            'previousEnd'         => $previousEnd,
            'monthLabel'          => $currentStart->translatedFormat('F Y'),
            'prevMonthLabel'      => $previousStart->translatedFormat('F Y'),
            'daysInMonth'         => $daysInMonth,
            'daysElapsed'         => $daysElapsed,
            'daysRemaining'       => $daysRemaining,
            'monthProgress'       => $monthProgress,
            'currentTotal'        => $currentTotal,
            'previousTotal'       => $previousTotal,
            'delta'               => $delta,
            'deltaAbs'            => $deltaAbs,
            'deltaPercent'        => $deltaPercent,
            'avgPerDay'           => $avgPerDay,
            'avgPerDayPrev'       => $avgPerDayPrev,
            'forecast'            => $forecast,
            'forecastDelta'       => $forecastDelta,
            'dailyCurrent'        => $dailyCurrent,
            'dailyPrevious'       => $dailyPrevious,
            'cumulativeCurrent'   => $cumulativeCurrent,
            'cumulativePrevious'  => $cumulativePrevious,
            'userBreakdown'       => $userBreakdown,
            'categoryComparison'  => $categoryComparison,
            'maxCategoryTotal'    => $maxCategoryTotal,
            'bestDay'             => $bestDay,
            'bestDayAmount'       => $minVal ?? 0,
            'worstDay'            => $worstDay,
            'worstDayAmount'      => $maxVal ?? 0,
        ];
    }
}
