<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\Base\CreateBase;

class CreateExpense extends CreateBase
{
    protected static string $resource = ExpenseResource::class;

    public function mount(): void
    {
        parent::mount();

        if (request()->has('data')) {
            $this->form->fill(request('data'));
        }
    }
}
