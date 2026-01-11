<?php

namespace App\Filament\Resources\ExpenseChangeRequestResource\Pages;

use App\Filament\Resources\ExpenseChangeRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpenseChangeRequest extends EditRecord
{
    protected static string $resource = ExpenseChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === 'pending'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}