<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Base\ExpensesGroupedChartWidget;

class ExpensesByUserChart extends ExpensesGroupedChartWidget
{
    protected string $chartType = 'doughnut';

    protected ?int $resultsLimit = null;

    protected ?string $heading = 'Доля затрат по пользователям';

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 5;

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

    protected function getDatasetStyle(): array
    {
        return [
            'backgroundColor' => [
                '#ff4d6d',
                '#7b61ff',
                '#ff8a00',
                '#00b894',
                '#38bdf8',
                '#a3a3a3',
            ],
        ];
    }
}

