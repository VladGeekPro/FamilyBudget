<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsCurrentMonthExpenses extends BaseWidget
{
    protected ?string $heading = 'Расходы пользователей за текущий месяц';

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 1;

    protected function getColumns(): int|array
    {
        return 2;
    }

    protected function getStats(): array
    {
        // $today = Carbon::today();
        $today = Carbon::today()->subMonth(2)->endOfMonth();
        $startOfMonth = $today->copy()->startOfMonth();

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $totalsByUser = Expense::query()
            ->whereBetween('date', [$startOfMonth->toDateString(), $today->toDateString()])
            ->selectRaw('user_id, COALESCE(SUM(sum), 0) as total_sum, COUNT(*) as expenses_count')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $dailyTotalsByUser = Expense::query()
            ->whereBetween('date', [$startOfMonth->toDateString(), $today->toDateString()])
            ->selectRaw('user_id, DATE(date) as expense_date, COALESCE(SUM(sum), 0) as day_total')
            ->groupBy('user_id', 'expense_date')
            ->get()
            ->groupBy('user_id')
            ->map(fn($rows) => $rows->keyBy('expense_date'));

        $monthTotal = (float) $totalsByUser->sum('total_sum');
        $monthTotalFormatted = $this->formatMoney($monthTotal);

        return $users
            ->map(function (User $user, int $index) use ($totalsByUser, $dailyTotalsByUser, $monthTotal, $monthTotalFormatted, $startOfMonth, $today) {
                $userTotals = $totalsByUser->get($user->id);
                $userDailyTotals = $dailyTotalsByUser->get($user->id);

                $userSum = (float) ($userTotals->total_sum ?? 0);
                $userExpensesCount = (int) ($userTotals->expenses_count ?? 0);
                $share = $monthTotal > 0 ? round(($userSum / $monthTotal) * 100, 1) : 0;
                $chart = [];
                $day = $startOfMonth->copy();

                while ($day->lte($today)) {
                    $dateKey = $day->toDateString();
                    $chart[] = (float) ($userDailyTotals?->get($dateKey)->day_total ?? 0);
                    $day->addDay();
                }

                return Stat::make($user->name, $this->formatMoney($userSum))
                    ->description("{$userExpensesCount} трат(ы), {$share}% от общей суммы {$monthTotalFormatted}")
                    ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                    ->color($this->resolveColor($index))
                    ->chart($chart)
                    ->extraAttributes([
                        'class' => 'min-h-[120px]',
                        'aria-label' => "{$user->name}: {$this->formatMoney($userSum)} за текущий месяц",
                    ]);
            })
            ->all();
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' MDL';
    }

    private function resolveColor(int $index): string
    {
        $palette = ['primary', 'success', 'warning', 'info', 'gray'];

        return $palette[$index % count($palette)];
    }
}
