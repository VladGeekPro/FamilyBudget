<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function updated($propertyName): void
    {
        if (str_starts_with($propertyName, 'tableDeferredFilters')) {
            session(['tableDeferredFilters.user' => $this->tableDeferredFilters['user']['values']]);
            session(['tableDeferredFilters.category' => $this->tableDeferredFilters['category']['values']]);
            session(['tableDeferredFilters.supplier' => $this->tableDeferredFilters['supplier']['values']]);
            session(['tableDeferredFilters.date' => $this->tableDeferredFilters['date']]);
            session(['tableDeferredFilters.sum' => $this->tableDeferredFilters['sum']]);
        } elseif ($propertyName === 'tableSearch') {
            session(['tableDeferredFilters.search' => trim($this->tableSearch)]);
        }
    }


    public function resetTableFiltersForm(): void
    {
        parent::resetTableFiltersForm();

        // do not forget about tableDeferredFilters.search
        session()->forget([
            'tableDeferredFilters.user',
            'tableDeferredFilters.category',
            'tableDeferredFilters.supplier',
            'tableDeferredFilters.date',
            'tableDeferredFilters.sum',
        ]);
    }
}
