<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Base\ExpensesGroupedChartWidget;
use App\Filament\Widgets\Concerns\HasBeautifulHeading;
use Filament\Support\RawJs;

class ExpensesByUserChart extends ExpensesGroupedChartWidget
{
    use HasBeautifulHeading;

    protected string $view = 'filament.widgets.beautiful-chart-widget';

    protected string $chartType = 'doughnut';

    protected ?int $resultsLimit = null;

    protected string $color = 'success';

    protected ?string $maxHeight = '350px';

    protected bool $isCollapsible = true;

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 6;

    protected function getHeaderGradient(): string
    {
        return 'from-emerald-600 via-green-600 to-teal-600';
    }

    protected function getHeaderIcon(): string
    {
        return 'heroicon-o-user-group';
    }

    protected function getHeaderTitle(): string
    {
        return 'Доля затрат по пользователям';
    }

    protected function getHeaderPill(): ?string
    {
        $rows = $this->getDataQuery()->get();
        $total = $rows->sum('total');
        $count = $rows->count();

        return number_format((float) $total, 0, ',', ' ') . ' MDL • ' . $count . ' уч.';
    }

    protected function getHeaderDescription(): ?string
    {
        $rows = $this->getDataQuery()->get();
        $total = (float) $rows->sum('total');

        if ($rows->isEmpty() || $total <= 0) {
            return 'Нет данных за выбранный период';
        }

        return $rows->map(function ($row) use ($total) {
            $pct = round((float) $row->total / $total * 100, 1);
            return e($row->label) . ': ' . $pct . '%';
        })->implode(' • ');
    }

    protected function getData(): array
    {
        $rows = $this->getDataQuery()->get();
        $total = (float) $rows->sum('total');

        $labels = $rows->map(function ($row) use ($total) {
            $pct = $total > 0 ? round((float) $row->total / $total * 100, 1) : 0;
            return $row->label . ' (' . $pct . '%)';
        })->all();

        $values = $rows
            ->pluck('total')
            ->map(static fn($v): float => round((float) $v, 2))
            ->all();

        $colors = array_slice([
            '#ff4d6d', '#7b61ff', '#ff8a00', '#00b894', '#38bdf8', '#a3a3a3',
        ], 0, count($values));

        $hoverColors = array_slice([
            '#ff2d55', '#6a4fff', '#e67a00', '#00a884', '#1da8f0', '#8a8a8a',
        ], 0, count($values));

        return [
            'datasets' => [
                [
                    'label' => 'Расходы, MDL',
                    'data' => $values,
                    'backgroundColor' => $colors,
                    'hoverBackgroundColor' => $hoverColors,
                    'borderColor' => 'rgba(255,255,255,0.8)',
                    'borderWidth' => 3,
                    'hoverBorderColor' => '#ffffff',
                    'hoverBorderWidth' => 4,
                    'hoverOffset' => 12,
                    'spacing' => 2,
                    'borderRadius' => 4,
                    'weight' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            cutout: '58%',
            radius: '90%',
            rotation: -90,
            circumference: 360,
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 800,
                easing: 'easeOutQuart',
            },
            layout: {
                padding: { top: 8, bottom: 8, left: 8, right: 8 }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    align: 'center',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 10,
                        boxHeight: 10,
                        padding: 14,
                        font: { size: 12, weight: '500' },
                        generateLabels: function(chart) {
                            const data = chart.data;
                            if (!data.labels || !data.labels.length) return [];
                            const ds = data.datasets[0];
                            return data.labels.map((label, i) => ({
                                text: label,
                                fillStyle: ds.backgroundColor[i],
                                strokeStyle: ds.borderColor || '#fff',
                                lineWidth: 1,
                                pointStyle: 'circle',
                                hidden: chart.getDatasetMeta(0).data[i]?.hidden,
                                index: i,
                            }));
                        },
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
                    displayColors: true,
                    caretSize: 6,
                    caretPadding: 8,
                    callbacks: {
                        title: function(items) {
                            return items[0]?.label?.split(' (')[0] || '';
                        },
                        label: function(context) {
                            const value = context.parsed;
                            const formatted = new Intl.NumberFormat('ru-RU', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }).format(value);
                            return '  Сумма: ' + formatted + ' MDL';
                        },
                        afterLabel: function(context) {
                            const ds = context.dataset;
                            const total = ds.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
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
        return 'users';
    }

    protected function getForeignKey(): string
    {
        return 'user_id';
    }

    protected function getFallbackLabel(): string
    {
        return 'Без пользователя';
    }

    protected function getDatasetLabel(): string
    {
        return 'Доля расходов';
    }
}

