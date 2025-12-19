<?php

namespace App\Filament\Resources\OverpaymentResource\Pages;

use App\Filament\Resources\OverpaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOverpayment extends CreateRecord
{
    protected static string $resource = OverpaymentResource::class;
}
