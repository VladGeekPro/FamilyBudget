<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithExpenseFilters;
use App\Models\User;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsCurrentMonthExpenses extends BaseWidget
{
    use InteractsWithExpenseFilters;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Расходы пользователей по выбранным фильтрам';

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int | array
    {
        return 2;
    }

    protected function getStats(): array
    {
        [$start, $end] = $this->resolveDateRangeFromFilters();

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $totalsByUser = $this->expenseQuery()
            ->selectRaw('user_id, COALESCE(SUM(sum), 0) as total_sum, COUNT(*) as expenses_count')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $dailyTotalsByUser = $this->expenseQuery()
            ->selectRaw('user_id, DATE(date) as expense_date, COALESCE(SUM(sum), 0) as day_total')
            ->groupBy('user_id', 'expense_date')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->keyBy('expense_date'));

        $periodTotal = (float) $totalsByUser->sum('total_sum');
        $periodTotalFormatted = $this->formatMoney($periodTotal);

        return $users
            ->map(function (User $user, int $index) use ($totalsByUser, $dailyTotalsByUser, $periodTotal, $periodTotalFormatted, $start, $end) {
                $userTotals = $totalsByUser->get($user->id);
                $userDailyTotals = $dailyTotalsByUser->get($user->id);

                $userSum = (float) ($userTotals?->total_sum ?? 0);
                $userExpensesCount = (int) ($userTotals?->expenses_count ?? 0);
                $share = $periodTotal > 0 ? round(($userSum / $periodTotal) * 100, 1) : 0;
                $chart = [];
                $day = $start->copy();

                while ($day->lte($end)) {
                    $dateKey = $day->toDateString();
                    $chart[] = (float) ($userDailyTotals?->get($dateKey)->day_total ?? 0);
                    $day->addDay();
                }

                return Stat::make($user->name, $this->formatMoney($userSum))
                    ->description("{$userExpensesCount} трат(ы), {$share}% от общей суммы {$periodTotalFormatted}")
                    ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                    ->color($this->resolveColor($index))
                    ->chart($chart)
                    ->extraAttributes([
                        'class' => 'min-h-[120px]',
                        'aria-label' => "{$user->name}: {$this->formatMoney($userSum)}",
                    ]);
            })
            ->all();
    }

    private function resolveColor(int $index): string
    {
        $palette = ['primary', 'success', 'warning', 'info', 'gray'];

        return $palette[$index % count($palette)];
    }
}
