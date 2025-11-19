<?php

namespace App\Filament\Resources\Base;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Lang;

abstract class CreateBase extends CreateRecord
{

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $model = $this->getResource()::getModel();
        $resourceName = Str::snake(class_basename($model));
        $resourceTranslation = "resources.notifications.create.{$resourceName}";

        if (!Lang::has($resourceTranslation)) {
            return __('resources.notifications.skip.create', ['resourceName' => $resourceName]);
        }

        return __($resourceTranslation);
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->label(__('resources.buttons.create_another'));
    }
}
