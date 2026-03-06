<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Support\Tables\Concerns\HasLayoutColumnManager;
use Filament\Resources\Pages\ListRecords as BaseListRecords;

abstract class ListRecords extends BaseListRecords
{
    use HasLayoutColumnManager;
}
