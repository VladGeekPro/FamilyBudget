<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Base\ExpensesGroupedChartWidget;
use App\Filament\Traits\HasBeautifulHeading;
use Filament\Support\RawJs;

class ExpensesByCategoryChart extends ExpensesGroupedChartWidget
{
    use HasBeautifulHeading;

    protected string $view = 'filament.widgets.beautiful-chart-widget';

    protected string $chartType = 'polarArea';

    protected string $color = 'danger';

    protected ?string $maxHeight = '380px';

    protected bool $isCollapsible = true;

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 4;

    protected ?int $resultsLimit = 8;

    protected function getHeaderGradient(): string
    {
        return 'from-rose-600 via-red-600 to-pink-600';
    }

    protected function getHeaderIcon(): string
    {
        return 'heroicon-o-tag';
    }

    protected function getHeaderTitle(): string
    {
        return __('resources.widgets.charts.by_category_title');
    }

    protected function getHeaderPill(): ?string
    {
        $rows = $this->getDataQuery()->get();
        $total = (float) $rows->sum('total');
        $count = $rows->count();

        return number_format($total, 0, ',', ' ') . ' MDL • ' . $count . ' кат.';
    }

    protected function getHeaderDescription(): ?string
    {
        $rows = $this->getDataQuery()->get();
        $total = (float) $rows->sum('total');

        if ($rows->isEmpty() || $total <= 0) {
            return 'Нет данных за выбранный период';
        }

        $top = $rows->first();
        $topPct = round((float) $top->total / $total * 100, 1);

        return 'Лидер: ' . e($top->label) . ' (' . $topPct . '%) • Топ-' . $rows->count() . ' категорий';
    }

    protected function getData(): array
    {
        $rows = $this->getDataQuery()->get();
        $total = (float) $rows->sum('total');

        $labels = $rows->map(function ($row) use ($total) {
            $pct = $total > 0 ? round((float) $row->total / $total * 100, 1) : 0;
            return $row->label . ' — ' . $pct . '%';
        })->all();

        $values = $rows->pluck('total')
            ->map(static fn($v): float => round((float) $v, 2))
            ->all();

        $colors = array_slice([
            'rgba(255, 77, 109, 0.75)',
            'rgba(123, 97, 255, 0.75)',
            'rgba(255, 138, 0, 0.75)',
            'rgba(0, 184, 148, 0.75)',
            'rgba(56, 189, 248, 0.75)',
            'rgba(244, 114, 182, 0.75)',
            'rgba(250, 204, 21, 0.75)',
            'rgba(163, 163, 163, 0.75)',
        ], 0, count($values));

        $borderColors = array_slice([
            '#ff4d6d', '#7b61ff', '#ff8a00', '#00b894',
            '#38bdf8', '#f472b6', '#facc15', '#a3a3a3',
        ], 0, count($values));

        return [
            'datasets' => [
                [
                    'label' => __('resources.widgets.charts.dataset_expenses_mdl'),
                    'data' => $values,
                    'backgroundColor' => $colors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 2,
                    'hoverBorderWidth' => 3,
                    'hoverBorderColor' => '#ffffff',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 800,
                easing: 'easeOutQuart',
            },
            layout: {
                padding: { top: 4, bottom: 4, left: 4, right: 4 },
            },
            scales: {
                r: {
                    display: true,
                    grid: { color: 'rgba(148, 163, 184, 0.12)' },
                    ticks: { display: false, backdropColor: 'transparent' },
                    pointLabels: { display: false },
                },
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    align: 'center',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'rectRounded',
                        boxWidth: 10,
                        boxHeight: 10,
                        padding: 10,
                        font: { size: 11, weight: '500' },
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
                    callbacks: {
                        title: function(items) {
                            return items[0]?.label?.split(' — ')[0] || '';
                        },
                        label: function(context) {
                            const formatted = new Intl.NumberFormat('ru-RU', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }).format(context.parsed.r);
                            return '  Сумма: ' + formatted + ' MDL';
                        },
                        afterLabel: function(context) {
                            const ds = context.dataset;
                            const total = ds.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((context.parsed.r / total) * 100).toFixed(1) : 0;
                            return '  Доля: ' + pct + '%';
                        },
                        footer: function(items) {
                            const ds = items[0]?.dataset;
                            if (!ds) return '';
                            const total = ds.data.reduce((a, b) => a + b, 0);
                            const formatted = new Intl.NumberFormat('ru-RU', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }).format(total);
                            return '─────────────\nВсего: ' + formatted + ' MDL';
                        },
                    },
                },
            },
        }
        JS);
    }

    protected function getJoinTable(): string
    {
        return 'categories';
    }

    protected function getForeignKey(): string
    {
        return 'category_id';
    }

    protected function getFallbackLabel(): string
    {
        return 'Без категории';
    }

    protected function getDatasetLabel(): string
    {
        return __('resources.widgets.charts.dataset_expense_sum_mdl');
    }
}

