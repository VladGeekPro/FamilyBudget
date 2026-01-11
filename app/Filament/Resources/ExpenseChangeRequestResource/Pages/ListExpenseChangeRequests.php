<?php

namespace App\Filament\Resources\ExpenseChangeRequestResource\Pages;

use App\Filament\Resources\ExpenseChangeRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenseChangeRequests extends ListRecords
{
    protected static string $resource = ExpenseChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Новый запрос'),
        ];
    }
}