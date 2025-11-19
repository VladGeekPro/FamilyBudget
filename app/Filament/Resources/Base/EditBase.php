<?php

namespace App\Filament\Resources\Base;

use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Lang;

abstract class EditBase extends EditRecord
{

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        $model = $this->getResource()::getModel();
        $resourceName = Str::snake(class_basename($model));
        $resourceTranslation = "resources.notifications.edit.{$resourceName}";

        if (!Lang::has($resourceTranslation)) {
            return __('resources.notifications.missing.edit', ['resourceName' => $resourceName]);
        }

        return __($resourceTranslation);
    }
}
