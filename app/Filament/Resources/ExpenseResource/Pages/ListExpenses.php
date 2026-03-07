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
        if (str_starts_with($propertyName, 'tableFilters')) {
            session(['tableFilters.user' => $this->tableFilters['user']['values']]);
            session(['tableFilters.category' => $this->tableFilters['category']['values']]);
            session(['tableFilters.supplier' => $this->tableFilters['supplier']['values']]);
            session(['tableFilters.date' => $this->tableFilters['date']]);
            session(['tableFilters.sum' => $this->tableFilters['sum']]);
        } elseif ($propertyName === 'tableSearch') {
            session(['tableFilters.search' => trim($this->tableSearch)]);
        }
    }


    public function resetTableFiltersForm(): void
    {
        parent::resetTableFiltersForm();

        // do not forget about tableFilters.search
        session()->forget([
            'tableFilters.user',
            'tableFilters.category',
            'tableFilters.supplier',
            'tableFilters.date',
            'tableFilters.sum',
        ]);
    }
}
