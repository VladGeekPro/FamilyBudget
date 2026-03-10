<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasBeautifulHeading;
use App\Filament\Traits\InteractsWithExpenseFilters;
use App\Models\User;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class ExpensesMonthlyTrendChart extends ChartWidget
{
    use HasBeautifulHeading;
    use InteractsWithExpenseFilters;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.beautiful-chart-widget';

    protected string $color = 'warning';

    protected ?string $maxHeight = null;

    protected bool $isCollapsible = true;

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected function getHeaderGradient(): string
    {
        return 'from-amber-600 via-orange-600 to-red-600';
    }

    protected function getHeaderIcon(): string
    {
        return 'heroicon-o-arrow-trending-up';
    }

    protected function getHeaderTitle(): string
    {
        return __('resources.widgets.charts.monthly_trend_title');
    }

    protected function getHeaderPill(): ?string
    {
        $from = now()->subMonths(5)->translatedFormat('M Y');
        $to   = now()->translatedFormat('M Y');

        $end   = now()->endOfMonth();
        $start = now()->startOfMonth()->subMonths(5);
        $total = $this->expenseQuery(includeDateRange: false)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->sum('sum');

        return $from . ' — ' . $to . ' • ' . number_format((float) $total, 0, ',', ' ') . ' MDL';
    }

    protected function getHeaderDescription(): ?string
    {
        $end = now()->endOfMonth();

        $currentMonth = $this->expenseQuery(includeDateRange: false)
            ->whereDate('date', '>=', now()->startOfMonth()->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->sum('sum');
        $prevMonth = $this->expenseQuery(includeDateRange: false)
            ->whereDate('date', '>=', now()->subMonthNoOverflow()->startOfMonth()->toDateString())
            ->whereDate('date', '<=', now()->subMonthNoOverflow()->endOfMonth()->toDateString())
            ->sum('sum');

        $delta = $prevMonth > 0 ? round(($currentMonth - $prevMonth) / $prevMonth * 100, 1) : 0;
        $arrow = $delta >= 0 ? '↑' : '↓';
        $deltaStr = ($delta >= 0 ? '+' : '') . $delta . '%';

        return '6 месяцев, по пользователям + итого • Текущий vs прошлый: ' . $deltaStr . ' ' . $arrow;
    }

    protected function getData(): array
    {
        $end    = now()->endOfMonth();
        $start  = now()->startOfMonth()->subMonths(5);

        $labels  = [];
        $months  = [];
        $cursor  = $start->copy();
        while ($cursor->lte($end)) {
            $key       = $cursor->format('Y-m-01');
            $labels[]  = $cursor->translatedFormat('M Y');
            $months[]  = $key;
            $cursor->addMonth();
        }

        // Total line
        $totalRows = $this->expenseQuery(includeDateRange: false)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw("strftime('%Y-%m-01', date) as month_key, COALESCE(SUM(sum), 0) as total")
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->keyBy('month_key');

        $totalValues = array_map(
            fn($mk) => round((float) ($totalRows->get($mk)?->total ?? 0), 2),
            $months
        );

        $userColors = [
            ['line' => '#ff4d6d', 'fill' => 'rgba(255, 77, 109, 0.10)'],
            ['line' => '#7b61ff', 'fill' => 'rgba(123, 97, 255, 0.10)'],
            ['line' => '#38bdf8', 'fill' => 'rgba(56, 189, 248, 0.10)'],
            ['line' => '#00b894', 'fill' => 'rgba(0, 184, 148, 0.10)'],
        ];

        $datasets = [
            [
                'label'                => 'Итого',
                'data'                 => $totalValues,
                'borderColor'          => '#94a3b8',
                'backgroundColor'      => 'rgba(148, 163, 184, 0.06)',
                'borderWidth'          => 2,
                'borderDash'           => [6, 4],
                'pointBackgroundColor' => '#94a3b8',
                'pointBorderColor'     => '#ffffff',
                'pointBorderWidth'     => 2,
                'pointRadius'          => 4,
                'pointHoverRadius'     => 7,
                'pointHoverBorderWidth'=> 3,
                'fill'                 => true,
                'tension'              => 0.4,
                'order'                => 99,
            ],
        ];

        $users = User::orderBy('name')->get();
        foreach ($users as $i => $user) {
            $color = $userColors[$i % count($userColors)];

            $rows = $this->expenseQuery(includeDateRange: false)
                ->where('expenses.user_id', $user->id)
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->selectRaw("strftime('%Y-%m-01', date) as month_key, COALESCE(SUM(sum), 0) as total")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->keyBy('month_key');

            $values = array_map(
                fn($mk) => round((float) ($rows->get($mk)?->total ?? 0), 2),
                $months
            );

            $datasets[] = [
                'label'                 => $user->name,
                'data'                  => $values,
                'borderColor'           => $color['line'],
                'backgroundColor'       => $color['fill'],
                'borderWidth'           => 2.5,
                'pointBackgroundColor'  => $color['line'],
                'pointBorderColor'      => '#ffffff',
                'pointBorderWidth'      => 2,
                'pointRadius'           => 5,
                'pointHoverRadius'      => 8,
                'pointHoverBorderWidth' => 3,
                'pointStyle'            => 'circle',
                'fill'                  => true,
                'tension'               => 0.4,
                'order'                 => $i,
            ];
        }

        return [
            'labels'   => $labels,
            'datasets' => $datasets,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getMaxHeight(): ?string
    {
        return '350px';
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            animation: {
                duration: 1000,
                easing: 'easeOutQuart',
            },
            layout: {
                padding: { top: 4, bottom: 0, left: 0, right: 0 },
            },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            hover: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    display: true,
                    grid: {
                        display: false,
                    },
                    border: { display: false },
                    ticks: {
                        font: { size: 11, weight: '500' },
                        padding: 8,
                    },
                },
                y: {
                    display: true,
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.08)',
                        drawTicks: false,
                    },
                    border: { display: false },
                    ticks: {
                        font: { size: 11 },
                        padding: 12,
                        callback: function(value) {
                            if (value >= 1000) return (value / 1000).toFixed(1) + 'k MDL';
                            return value + ' MDL';
                        },
                    },
                },
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'center',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        boxHeight: 8,
                        padding: 16,
                        font: { size: 12, weight: '500' },
                    },
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleColor: '#f8fafc',
                    bodyColor: '#e2e8f0',
                    footerColor: '#94a3b8',
                    borderColor: 'rgba(148, 163, 184, 0.2)',
                    borderWidth: 1,
                    cornerRadius: 10,
                    padding: 14,
                    boxPadding: 6,
                    usePointStyle: true,
                    titleFont: { size: 13, weight: '600' },
                    bodyFont: { size: 12 },
                    footerFont: { size: 11, style: 'italic' },
                    caretSize: 6,
                    caretPadding: 8,
                    mode: 'index',
                    intersect: false,
                    itemSort: function(a, b) {
                        return b.parsed.y - a.parsed.y;
                    },
                    callbacks: {
                        label: function(context) {
                            const formatted = new Intl.NumberFormat('ru-RU', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }).format(context.parsed.y);
                            return '  ' + context.dataset.label + ': ' + formatted + ' MDL';
                        },
                        footer: function(items) {
                            const total = items.reduce((sum, item) => {
                                if (item.dataset.label === 'Итого') return sum;
                                return sum + item.parsed.y;
                            }, 0);
                            const formatted = new Intl.NumberFormat('ru-RU', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }).format(total);
                            return '─────────────\nСумма участников: ' + formatted + ' MDL';
                        },
                    },
                },
            },
        }
        JS);
    }
}

