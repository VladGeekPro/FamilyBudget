<?php

namespace App\Filament\Resources\ExpenseChangeRequestResource\Pages;

use App\Filament\Resources\Base\EditBase;
use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\User;
use App\Notifications\ExpenseChangeRequestModified;
use Filament\Actions;

class EditExpenseChangeRequest extends EditBase
{
    protected static string $resource = ExpenseChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->status === 'pending')
                ->successNotificationTitle(fn() => $this->getDeletedNotificationTitle())
                ->after(function () {
                    $users = User::all();
                    foreach ($users as $user) {
                        $user->notify(new ExpenseChangeRequestModified($this->record, 'delete'));
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $user->notify(new ExpenseChangeRequestModified($this->record, 'edit'));
        }
    }
}
