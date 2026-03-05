<?php

namespace App\Filament\Widgets\Base;

use App\Filament\Widgets\Concerns\InteractsWithExpenseFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

abstract class ExpensesGroupedChartWidget extends ChartWidget
{
    use InteractsWithExpenseFilters;
    use InteractsWithPageFilters;

    protected string $chartType = 'bar';

    protected ?int $resultsLimit = 12;

    protected ?string $maxHeight = '320px';

    abstract protected function getJoinTable(): string;

    abstract protected function getForeignKey(): string;

    protected function getLabelColumn(): string
    {
        return 'name';
    }

    protected function getFallbackLabel(): string
    {
        return 'N/A';
    }

    protected function getDatasetLabel(): string
    {
        return 'Amount, MDL';
    }

    protected function getDatasetStyle(): array
    {
        return [];
    }

    protected function getDataQuery(): Builder
    {
        $table = $this->getJoinTable();
        $labelColumn = $this->getLabelColumn();
        $fallbackLabel = addslashes($this->getFallbackLabel());

        $query = $this->expenseQuery()
            ->leftJoin($table, "{$table}.id", '=', "expenses.{$this->getForeignKey()}")
            ->selectRaw("COALESCE({$table}.{$labelColumn}, '{$fallbackLabel}') as label, COALESCE(SUM(expenses.sum), 0) as total")
            ->groupBy('label')
            ->orderByDesc('total');

        if ($this->resultsLimit !== null) {
            $query->limit($this->resultsLimit);
        }

        return $query;
    }

    protected function getData(): array
    {
        $rows = $this->getDataQuery()->get();

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [
                array_merge([
                    'label' => $this->getDatasetLabel(),
                    'data' => $rows
                        ->pluck('total')
                        ->map(static fn ($value): float => round((float) $value, 2))
                        ->all(),
                ], $this->getDatasetStyle()),
            ],
        ];
    }

    protected function getType(): string
    {
        return $this->chartType;
    }
}

