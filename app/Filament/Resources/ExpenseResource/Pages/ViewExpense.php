<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Filament\Resources\ExpenseResource;
use App\Models\ExpenseChangeRequest;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewExpense extends ViewRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        $headerActions = parent::getHeaderActions();

        $meta = ExpenseResource::getChangeRequestMeta($this->record);

        if ($meta) {
            $headerActions[] = Action::make($meta['name'])
                ->label($meta['label'])
                ->icon($meta['icon'])
                ->url($meta['url']);
        }

        return $headerActions;
    }
}
