<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithExpenseFilters;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthToMonthComparisonWidget extends BaseWidget
{
    use InteractsWithExpenseFilters;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Сравнение с прошлым месяцем';

    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int | array
    {
        return 3;
    }

    protected function getStats(): array
    {
        $currentStart = now()->startOfMonth();
        $currentEnd = now()->endOfMonth();
        $previousStart = now()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = now()->subMonthNoOverflow()->endOfMonth();

        $baseQuery = $this->expenseQuery(includeDateRange: false);

        $currentTotal = (float) (clone $baseQuery)
            ->whereBetween('date', [$currentStart->toDateString(), $currentEnd->toDateString()])
            ->sum('sum');

        $previousTotal = (float) (clone $baseQuery)
            ->whereBetween('date', [$previousStart->toDateString(), $previousEnd->toDateString()])
            ->sum('sum');

        $delta = $currentTotal - $previousTotal;
        $deltaAbs = abs($delta);
        $deltaPercent = $previousTotal > 0
            ? round(($delta / $previousTotal) * 100, 1)
            : ($currentTotal > 0 ? 100.0 : 0.0);

        $deltaColor = $delta <= 0 ? 'success' : 'danger';
        $deltaIcon = $delta <= 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up';
        $deltaText = ($delta <= 0 ? 'Меньше на ' : 'Больше на ') . $this->formatMoney($deltaAbs);

        return [
            Stat::make('Текущий месяц', $this->formatMoney($currentTotal))
                ->description($currentStart->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-calendar-days', IconPosition::Before)
                ->color('primary'),

            Stat::make('Предыдущий месяц', $this->formatMoney($previousTotal))
                ->description($previousStart->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-clock', IconPosition::Before)
                ->color('gray'),

            Stat::make('Изменение', ($delta > 0 ? '+' : '-') . abs($deltaPercent) . '%')
                ->description($deltaText)
                ->descriptionIcon($deltaIcon, IconPosition::Before)
                ->color($deltaColor),
        ];
    }
}
