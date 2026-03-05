<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithExpenseFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class ExpensesMonthlyTrendChart extends ChartWidget
{
    use InteractsWithExpenseFilters;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Общие расходы за последние 6 месяцев';

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $end = now()->endOfMonth();
        $start = now()->startOfMonth()->subMonths(5);
        $driver = DB::connection()->getDriverName();

        $monthExpression = match ($driver) {
            'sqlite' => "strftime('%Y-%m-01', date)",
            'pgsql' => "to_char(date_trunc('month', date), 'YYYY-MM-01')",
            default => "DATE_FORMAT(date, '%Y-%m-01')",
        };

        $rows = $this->expenseQuery(includeDateRange: false)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("{$monthExpression} as month_key, COALESCE(SUM(sum), 0) as total")
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->keyBy('month_key');

        $labels = [];
        $values = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $monthKey = $cursor->format('Y-m-01');
            $labels[] = $cursor->translatedFormat('M Y');
            $values[] = round((float) ($rows->get($monthKey)->total ?? 0), 2);
            $cursor->addMonth();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Расходы, MDL',
                    'data' => $values,
                    'borderColor' => '#ff4d6d',
                    'backgroundColor' => 'rgba(255, 77, 109, 0.16)',
                    'pointBackgroundColor' => '#7b61ff',
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

