<?php

namespace App\Filament\Resources\DebtResource\Pages;

use App\Filament\Resources\DebtResource;
use App\Filament\Resources\Base\EditBase;

use Filament\Actions;

class EditDebt extends EditBase
{
    protected static string $resource = DebtResource::class;

    protected function getFormActions(): array
    {
        $actions = [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];

        $copyAction = Actions\Action::make('copy')
            ->label(__('resources.buttons.copy'))
            ->color('info')
            ->icon('heroicon-o-document-duplicate')
            ->action(function () {
                $record = $this->record;

                $data = array_merge(
                    $record->only(['user_id', 'category_id', 'supplier_id', 'notes']),
                    ['date' => now()->format('Y-m-d')]
                );

                return redirect()->route(
                    'filament.admin.resources.expenses.create',
                    ['data' => $data]
                );
            });

        array_splice($actions, 1, 0, [$copyAction]);

        return $actions;
    }
}
