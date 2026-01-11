<?php

namespace App\Filament\Resources\ExpenseChangeRequestResource\Pages;

use App\Filament\Resources\ExpenseChangeRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewExpenseChangeRequest extends ViewRecord
{
    protected static string $resource = ExpenseChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === 'pending'),
        ];
    }
}