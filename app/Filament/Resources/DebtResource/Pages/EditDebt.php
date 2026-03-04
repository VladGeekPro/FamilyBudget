<?php

namespace App\Filament\Resources\DebtResource\Pages;

use App\Filament\Resources\DebtResource;
use App\Filament\Resources\Base\EditBase;
use App\Models\User;
use App\Notifications\DebtEditedNotification;

use Filament\Actions;
use Filament\Notifications\Notification;

class EditDebt extends EditBase
{
    protected static string $resource = DebtResource::class;

    protected function afterSave(): void
    {
        $editor = auth()->user();
        $debt = $this->record;

        $users = User::all();
        foreach ($users as $user) {
            $user->notify(new DebtEditedNotification($debt, $editor, 'edited_debt'));
        }
    }

}
