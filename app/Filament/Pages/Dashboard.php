<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $routePath = '/familyBudget';

    protected static ?string $title = 'Главная';

    public function getColumns(): int | array
    {
        return 2;
    }
}
