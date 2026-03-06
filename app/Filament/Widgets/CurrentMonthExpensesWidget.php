<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithExpenseFilters;
use App\Models\User;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class CurrentMonthExpensesWidget extends BaseWidget
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
            ->get(['id', 'name', 'image', 'email']);

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
            ->map(fn($rows) => $rows->keyBy('expense_date'));

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

                return Stat::make(
                    $this->buildUserHeading($user),
                    $this->formatMoney($userSum)
                )
                    ->description($this->buildUserDescription(
                        userExpensesCount: $userExpensesCount,
                        share: $share,
                        periodTotalFormatted: $periodTotalFormatted,
                        userSum: $userSum,
                    ))
                    ->descriptionIcon('heroicon-m-chart-bar-square', IconPosition::Before)
                    ->color($this->resolveColor($index))
                    ->chart($chart)
                    ->extraAttributes([
                        'class' => 'min-h-[152px] rounded-2xl ring-1 ring-gray-200/70 dark:ring-white/10 bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-800',
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

    protected function buildUserHeading(object $user): HtmlString
    {
        $name = e((string) ($user->name ?? 'Пользователь'));
        $email = (string) ($user->email ?? '');
        $avatarUrl = $user->image;

        if ($avatarUrl) {
            $avatar =  asset('storage/' . e($avatarUrl));

            return new HtmlString("
            <span class='inline-flex items-center gap-2'>
                <img src='{$avatar}' alt='{$name}' class='h-[40px] w-[40px] rounded-full object-cover ring-2 ring-white/80 dark:ring-white/20 shadow-sm' />
                <span class='font-semibold'>{$name}</span>
            </span>
        ");
        }

        $emoji = e(\App\Models\User::getIcon($email));

        return new HtmlString("
        <span class='inline-flex items-center gap-2'>
            <span class='inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-base'>{$emoji}</span>
            <span class='font-semibold'>{$name}</span>
        </span>
    ");
    }

    protected function buildUserDescription(
        int $userExpensesCount,
        string|int|float $share,
        string $periodTotalFormatted,
        float|int $userSum,
    ): HtmlString {

        return new HtmlString("
        <div class='text-xs opacity-90 inline-flex items-center gap-2 whitespace-nowrap'>
            <span>{$userExpensesCount} трат(ы)</span>
            <span class='opacity-60'>•</span>
            <span>{$share}% от {$periodTotalFormatted}</span>
        </div>
    ");
    }
}
