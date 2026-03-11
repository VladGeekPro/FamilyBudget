<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\ConfigurableWidget;
use App\Filament\Traits\InteractsWithExpenseFilters;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Concerns\InteractsWithHeaderActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Livewire\Attributes\Reactive;

class MonthToMonthComparisonWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithHeaderActions;
    use InteractsWithExpenseFilters;
    use ConfigurableWidget;

    protected string $view = 'filament.widgets.month-comparison-widget';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public array $pageFilters = [];

    public bool $showConfigureModal = false;

    public array $configureFormData = [];

    public function mount(): void
    {
        $this->configureFormData = $this->getFormData();
    }

    protected function getConfigurableSections(): array
    {
        return [
            'month_comparison' => 'Сравнение месяцев (карточки с итогами)',
            'cumulative_chart' => 'График накопительных итогов',
            'per_user' => 'Разбивка по пользователям',
            'categories' => 'Топ категорий',
            'result_banner' => 'Итоговый баннер (экономия/перерасход)',
        ];
    }

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
        $currentTotal = (clone $baseQuery)
            ->whereDate('date', '>=', $currentStart->toDateString())
            ->whereDate('date', '<=', $currentEnd->toDateString())
            ->sum('sum');

        $previousTotal = (clone $baseQuery)
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
            ->selectRaw('DATE(date) as d, SUM(sum) as t')
            ->whereDate('date', '>=', $currentStart->toDateString())
            ->whereDate('date', '<=', $currentEnd->toDateString())
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $dailyCurrent = [];
        $cursor = $currentStart->copy();
        while ($cursor->lte($currentEnd)) {
            $dailyCurrent[] = ($dailyRaw->get($cursor->toDateString())?->t ?? 0);
            $cursor->addDay();
        }

        // ── Per-day previous month ──
        $dailyPrevRaw = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('DATE(date) as d, SUM(sum) as t')
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
            $dailyPrevious[] = ($dailyPrevRaw->get($cursor->toDateString())?->t ?? 0);
            $cursor->addDay();
        }

        // ── Cumulative ──
        $cumulativeCurrent  = [];
        $cumulativePrevious = [];
        $sum = 0;
        foreach ($dailyCurrent as $v) { $sum += $v; $cumulativeCurrent[] = round($sum, 2); }
        $sum = 0;
        foreach ($dailyPrevious as $v) { $sum += $v; $cumulativePrevious[] = round($sum, 2); }

        // ── Per-user breakdown ──
        $allUsers = User::orderBy('name')->get();

        $currentByUser = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('user_id, SUM(sum) as total')
            ->whereDate('date', '>=', $currentStart->toDateString())
            ->whereDate('date', '<=', $currentEnd->toDateString())
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $previousByUser = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('user_id, SUM(sum) as total')
            ->whereDate('date', '>=', $previousStart->toDateString())
            ->whereDate('date', '<=', $previousEnd->toDateString())
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $userBreakdown = $allUsers->map(fn(User $u) => (object) [
            'user'         => $u,
            'current'      => ($currentByUser[$u->id] ?? 0),
            'previous'     => ($previousByUser[$u->id] ?? 0),
            'delta'        => ($currentByUser[$u->id] ?? 0) - ($previousByUser[$u->id] ?? 0),
            'deltaPercent' => ($previousByUser[$u->id] ?? 0) > 0
                ? round(((($currentByUser[$u->id] ?? 0) - ($previousByUser[$u->id] ?? 0)) / ($previousByUser[$u->id])) * 100, 1)
                : (($currentByUser[$u->id] ?? 0) > 0 ? 100.0 : 0.0),
        ])->values();

        // ── Top categories comparison ──
        $currentByCat = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('category_id, SUM(sum) as total')
            ->whereDate('date', '>=', $currentStart->toDateString())
            ->whereDate('date', '<=', $currentEnd->toDateString())
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $previousByCat = $this->expenseQuery(includeDateRange: false)
            ->selectRaw('category_id, SUM(sum) as total')
            ->whereDate('date', '>=', $previousStart->toDateString())
            ->whereDate('date', '<=', $previousEnd->toDateString())
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $allCatIds = $currentByCat->keys()->merge($previousByCat->keys())->unique();
        $categories = Category::whereIn('id', $allCatIds)->pluck('name', 'id');

        $categoryComparison = $allCatIds->map(fn($catId) => (object) [
            'name'     => $categories[$catId] ?? 'Без категории',
            'current'  => ($currentByCat[$catId] ?? 0),
            'previous' => ($previousByCat[$catId] ?? 0),
            'delta'    => ($currentByCat[$catId] ?? 0) - ($previousByCat[$catId] ?? 0),
        ])->sortByDesc('current')->take(5)->values();

        $maxCategoryTotal = $categoryComparison->max(fn($c) => max($c->current, $c->previous)) ?: 1;

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
            'dailyCurrent'        => $dailyCurrent,
            'dailyPrevious'       => $dailyPrevious,
            'cumulativeCurrent'   => $cumulativeCurrent,
            'cumulativePrevious'  => $cumulativePrevious,
            'userBreakdown'       => $userBreakdown,
            'categoryComparison'  => $categoryComparison,
            'maxCategoryTotal'    => $maxCategoryTotal,
            'sections'            => $this->getSectionSettings(),
        ];
    }
}
