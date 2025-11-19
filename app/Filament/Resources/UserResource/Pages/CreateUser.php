<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use App\Filament\Resources\Base\CreateBase;

class CreateUser extends CreateBase
{
    protected static string $resource = UserResource::class;

    public bool $changePasswordMode = true;
   
}
