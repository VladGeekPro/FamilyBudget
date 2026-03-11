<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasBeautifulHeading;
use App\Filament\Widgets\Base\ExpensesGroupedChartWidget;
use App\Models\User;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExpensesByDayOfWeekChart extends ExpensesGroupedChartWidget
{
    use HasBeautifulHeading;

    protected string $view = 'filament.widgets.beautiful-chart-widget';

    protected string $color = 'primary';

    protected bool $isCollapsible = true;

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 7;

    protected ?array $cachedDayData = null;

    protected function getHeaderGradient(): string
    {
        return 'from-sky-600 via-blue-600 to-indigo-600';
    }

    protected function getHeaderIcon(): string
    {
        return 'heroicon-o-calendar-days';
    }

    protected function getHeaderTitle(): string
    {
        return __('resources.widgets.charts.by_day_title');
    }

    protected function getHeaderPill(): ?string
    {
        $data = $this->getDayData();
        $maxDay = $data['dayNames'][array_search(max($data['totalByDay']), $data['totalByDay'])] ?? '—';

        return 'Макс: ' . $maxDay;
    }

    protected function getHeaderDescription(): ?string
    {
        $data = $this->getDayData();
        $total = array_sum($data['totalByDay']);

        if ($total <= 0) {
            return 'Нет данных за выбранный период';
        }

        $maxIdx = array_search(max($data['totalByDay']), $data['totalByDay']);
        $minIdx = array_search(min(array_filter($data['totalByDay'], fn($v) => $v > 0) ?: [0]), $data['totalByDay']);
        $maxDay = $data['dayNames'][$maxIdx] ?? '?';
        $minDay = $data['dayNames'][$minIdx] ?? '?';

        return 'Пик: ' . $maxDay . ' • Мин: ' . $minDay . ' • Радарная диаграмма';
    }

    private function getDayData(): array
    {
        if ($this->cachedDayData !== null) {
            return $this->cachedDayData;
        }

        $dayNames = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        $rows = $this->getDataQuery()->get()->keyBy('dow');

        $mapDow = function (int $dbDow): int {
            return $dbDow === 0 ? 6 : $dbDow - 1;
        };

        $totalByDay = array_fill(0, 7, 0.0);
        foreach ($rows as $dbDow => $row) {
            $idx = $mapDow((int) $dbDow);
            if ($idx >= 0 && $idx < 7) {
                $totalByDay[$idx] = round($row->total, 2);
            }
        }

        $userColors = [
            ['line' => '#ff4d6d', 'fill' => 'rgba(255, 77, 109, 0.15)'],
            ['line' => '#7b61ff', 'fill' => 'rgba(123, 97, 255, 0.15)'],
            ['line' => '#38bdf8', 'fill' => 'rgba(56, 189, 248, 0.15)'],
            ['line' => '#00b894', 'fill' => 'rgba(0, 184, 148, 0.15)'],
        ];

        $users = User::orderBy('name')->get();
        $userDatasets = [];

        foreach ($users as $i => $user) {
            $color = $userColors[$i % count($userColors)];

            $driver = DB::connection()->getDriverName();
            $dowExpression = match ($driver) {
                'sqlite' => "CAST(strftime('%w', date) AS INTEGER)",
                'pgsql' => "EXTRACT(DOW FROM date)::integer",
                default => "DAYOFWEEK(date)",
            };

            $userRows = $this->expenseQuery()
                ->where('expenses.user_id', $user->id)
                ->selectRaw("{$dowExpression} as dow, SUM(sum) as total")
                ->groupBy('dow')
                ->get()
                ->keyBy('dow');

            $values = array_fill(0, 7, 0.0);
            foreach ($userRows as $dbDow => $row) {
                $idx = $mapDow((int) $dbDow);
                if ($idx >= 0 && $idx < 7) {
                    $values[$idx] = round($row->total, 2);
                }
            }

            $userDatasets[] = [
                'label'                => $user->name,
                'data'                 => $values,
                'borderColor'          => $color['line'],
                'backgroundColor'      => $color['fill'],
                'borderWidth'          => 2.5,
                'pointBackgroundColor' => $color['line'],
                'pointBorderColor'     => '#ffffff',
                'pointBorderWidth'     => 2,
                'pointRadius'          => 5,
                'pointHoverRadius'     => 8,
                'pointHoverBorderWidth' => 3,
                'fill'                 => true,
            ];
        }

        return $this->cachedDayData = [
            'dayNames' => $dayNames,
            'totalByDay' => $totalByDay,
            'userDatasets' => $userDatasets,
        ];
    }

    protected function getDataQuery(): Builder
    {
        $driver = DB::connection()->getDriverName();
        $dowExpression = match ($driver) {
            'sqlite' => "CAST(strftime('%w', date) AS INTEGER)",
            'pgsql' => "EXTRACT(DOW FROM date)::integer",
            default => "DAYOFWEEK(date)",
        };

        return $this->expenseQuery()
            ->selectRaw("{$dowExpression} as dow, SUM(sum) as total")
            ->groupBy('dow');
    }

    protected function getData(): array
    {
        $data = $this->getDayData();

        $datasets = [
            [
                'label'                => 'Итого',
                'data'                 => $data['totalByDay'],
                'borderColor'          => '#94a3b8',
                'backgroundColor'      => 'rgba(148, 163, 184, 0.08)',
                'borderWidth'          => 2,
                'borderDash'           => [6, 4],
                'pointBackgroundColor' => '#94a3b8',
                'pointBorderColor'     => '#ffffff',
                'pointBorderWidth'     => 2,
                'pointRadius'          => 4,
                'pointHoverRadius'     => 7,
                'fill'                 => true,
            ],
        ];

        foreach ($data['userDatasets'] as $ds) {
            $datasets[] = $ds;
        }

        return [
            'labels'   => $data['dayNames'],
            'datasets' => $datasets,
        ];
    }

    protected function getType(): string
    {
        return 'radar';
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            animation: {
                duration: 800,
                easing: 'easeOutQuart',
            },
            layout: {
                padding: { top: 4, bottom: 4, left: 4, right: 4 },
            },
            scales: {
                r: {
                    display: true,
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.15)',
                        circular: true,
                    },
                    angleLines: {
                        color: 'rgba(148, 163, 184, 0.12)',
                    },
                    pointLabels: {
                        display: true,
                        font: { size: 12, weight: '600' },
                        padding: 12,
                    },
                    ticks: {
                        display: false,
                        backdropColor: 'transparent',
                    },
                },
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
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
                    itemSort: function(a, b) {
                        return b.parsed.r - a.parsed.r;
                    },
                    callbacks: {
                        label: function(context) {
                            const formatted = new Intl.NumberFormat('ru-RU', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }).format(context.parsed.r);
                            return '  ' + context.dataset.label + ': ' + formatted + ' MDL';
                        },
                        footer: function(items) {
                            const total = items.reduce((sum, item) => {
                                if (item.dataset.label === 'Итого') return sum;
                                return sum + item.parsed.r;
                            }, 0);
                            const formatted = new Intl.NumberFormat('ru-RU', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }).format(total);
                            return '─────────────\nСумма: ' + formatted + ' MDL';
                        },
                    },
                },
            },
        }
        JS);
    }
}
