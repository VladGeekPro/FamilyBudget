<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Base\ExpensesGroupedChartWidget;

class ExpensesByCategoryChart extends ExpensesGroupedChartWidget
{
    protected ?string $heading = 'Затраты по категориям';

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 3;

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
        return 'Сумма расходов, MDL';
    }

    protected function getDatasetStyle(): array
    {
        return [
            'backgroundColor' => '#ff4d6d',
            'borderRadius' => 8,
        ];
    }
}

