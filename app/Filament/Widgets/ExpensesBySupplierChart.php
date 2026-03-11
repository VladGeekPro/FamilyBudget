<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Base\ExpensesGroupedChartWidget;
use App\Filament\Traits\HasBeautifulHeading;
use Filament\Support\RawJs;

class ExpensesBySupplierChart extends ExpensesGroupedChartWidget
{
    use HasBeautifulHeading;

    protected string $view = 'filament.widgets.beautiful-chart-widget';

    protected string $chartType = 'bar';

    protected string $color = 'info';

    protected ?string $maxHeight = null;

    protected bool $isCollapsible = true;

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 5;

    protected function getHeaderGradient(): string
    {
        return 'from-violet-600 via-purple-600 to-fuchsia-600';
    }

    protected function getHeaderIcon(): string
    {
        return 'heroicon-o-building-storefront';
    }

    protected function getHeaderTitle(): string
    {
        return __('resources.widgets.charts.by_supplier_title');
    }

    protected function getHeaderPill(): ?string
    {
        $rows = $this->getGroupedRows();
        $total = $rows->sum('total');
        $count = $rows->count();

        return number_format($total, 0, ',', ' ') . ' MDL • ' . $count . ' пост.';
    }

    protected function getHeaderDescription(): ?string
    {
        $rows = $this->getGroupedRows();
        $total = $rows->sum('total');

        if ($rows->isEmpty() || $total <= 0) {
            return 'Нет данных за выбранный период';
        }

        $top = $rows->first();
        $topPct = round($top->total / $total * 100, 1);

        return '#1 ' . e($top->label) . ' (' . $topPct . '%) • Рейтинг топ-' . $rows->count();
    }

    protected function getData(): array
    {
        $rows = $this->getGroupedRows();
        $total = $rows->sum('total');

        $labels = $rows->map(fn($row) => $row->label)->all();

        $values = $rows->pluck('total')
            ->map(static fn($v) => round($v, 2))
            ->all();

        $n = count($values);
        $colors = [];
        $borderColors = [];
        for ($i = 0; $i < $n; $i++) {
            $ratio = $n > 1 ? $i / ($n - 1) : 0;
            $r = (int) round(123 + (56 - 123) * $ratio);
            $g = (int) round(97 + (189 - 97) * $ratio);
            $b = (int) round(255 + (248 - 255) * $ratio);
            $colors[] = "rgba({$r}, {$g}, {$b}, 0.75)";
            $borderColors[] = "rgb({$r}, {$g}, {$b})";
        }

        return [
            'datasets' => [
                [
                    'label' => __('resources.widgets.charts.dataset_expense_sum_mdl'),
                    'data' => $values,
                    'backgroundColor' => $colors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 2,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'hoverBorderWidth' => 3,
                    'hoverBorderColor' => '#ffffff',
                    'barPercentage' => 0.7,
                    'categoryPercentage' => 0.85,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            indexAxis: 'y',
            animation: {
                duration: 800,
                easing: 'easeOutQuart',
            },
            layout: {
                padding: { top: 4, bottom: 4, left: 0, right: 16 },
            },
            scales: {
                x: {
                    display: true,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.08)',
                        drawTicks: false,
                    },
                    border: { display: false },
                    ticks: {
                        font: { size: 11 },
                        padding: 8,
                        callback: function(value) {
                            if (value >= 1000) return (value / 1000).toFixed(1) + 'k';
                            return value;
                        },
                    },
                },
                y: {
                    display: true,
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        font: { size: 12, weight: '500' },
                        padding: 8,
                        mirror: false,
                        crossAlign: 'far',
                    },
                },
            },
            plugins: {
                legend: { display: false },
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
                    titleFont: { size: 13, weight: '600' },
                    bodyFont: { size: 12 },
                    footerFont: { size: 11, style: 'italic' },
                    caretSize: 6,
                    callbacks: {
                        label: function(context) {
                            const formatted = new Intl.NumberFormat('ru-RU', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }).format(context.parsed.x);
                            return '  Сумма: ' + formatted + ' MDL';
                        },
                        afterLabel: function(context) {
                            const ds = context.dataset;
                            const total = ds.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((context.parsed.x / total) * 100).toFixed(1) : 0;
                            return '  Доля: ' + pct + '% от отображённых';
                        },
                        footer: function(items) {
                            const idx = items[0]?.dataIndex;
                            if (idx !== undefined) return '  Позиция: #' + (idx + 1);
                            return '';
                        },
                    },
                },
            },
        }
        JS);
    }

    protected function getJoinTable(): string
    {
        return 'suppliers';
    }

    protected function getForeignKey(): string
    {
        return 'supplier_id';
    }

    protected function getFallbackLabel(): string
    {
        return 'Без поставщика';
    }

    protected function getDatasetLabel(): string
    {
        return __('resources.widgets.charts.dataset_expense_sum_mdl');
    }
}

