<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Base\ExpensesGroupedChartWidget;

class ExpensesBySupplierChart extends ExpensesGroupedChartWidget
{
    protected ?string $heading = 'Затраты по поставщикам';

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 4;

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
        return 'Сумма расходов, MDL';
    }

    protected function getDatasetStyle(): array
    {
        return [
            'backgroundColor' => '#7b61ff',
            'borderRadius' => 8,
        ];
    }
}

