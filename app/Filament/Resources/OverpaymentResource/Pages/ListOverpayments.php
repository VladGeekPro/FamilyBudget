<?php

namespace App\Filament\Resources\OverpaymentResource\Pages;

use App\Filament\Resources\OverpaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOverpayments extends ListRecords
{
    protected static string $resource = OverpaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
