<?php

namespace App\Filament\Widgets\Base;

use App\Filament\Traits\InteractsWithExpenseFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

abstract class ExpensesGroupedChartWidget extends ChartWidget
{
    use InteractsWithExpenseFilters;
    use InteractsWithPageFilters;

    protected string $chartType = 'bar';

    protected ?int $resultsLimit = 10;

    protected ?string $maxHeight = '350px';

    protected ?Collection $cachedGroupedRows = null;

    protected function getJoinTable(): string
    {
        return 'categories';
    }

    protected function getForeignKey(): string
    {
        return 'category_id';
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

        $query = $this->expenseQuery()
            ->leftJoin($table, "{$table}.id", '=', "expenses.{$this->getForeignKey()}")
            ->selectRaw("{$table}.name as label, SUM(expenses.sum) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit($this->resultsLimit);

        return $query;
    }

    protected function getGroupedRows(): Collection
    {
        return $this->cachedGroupedRows ??= $this->getDataQuery()->get();
    }

    protected function getData(): array
    {
        $rows = $this->getGroupedRows();

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [
                array_merge([
                    'label' => $this->getDatasetLabel(),
                    'data' => $rows
                        ->pluck('total')
                        ->map(static fn($value) => round($value, 2))
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
