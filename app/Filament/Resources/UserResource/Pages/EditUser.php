<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use App\Filament\Resources\Base\EditBase;


class EditUser extends EditBase
{
    protected static string $resource = UserResource::class;

    public bool $changePasswordMode = false;
  
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Action::make('change_password')
                ->label(__('resources.fields.change_password'))
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn(self $livewire) => !$this->changePasswordMode)
                ->action(function (self $livewire) {
                    $this->changePasswordMode = true;
                }),

            $this->getCancelFormAction(),
        ];
    }
}
