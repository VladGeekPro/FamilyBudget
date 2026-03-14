<?php

namespace App\Filament\Resources\ExpenseChangeRequestResource\Widgets;

use App\Filament\Traits\HasExpenseCardTableLayout;
use App\Models\Expense;
use Filament\Support\Enums\Width;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class SelectExpenseTableWidget extends BaseWidget
{
    use HasExpenseCardTableLayout;

    protected static ?string $heading = 'Выберите расход';

    public function table(Table $table): Table
    {
        return $table
            ->query(Expense::previousMonthsExpenses()->orderBy('date', 'desc'))
            ->searchPlaceHolder(__('resources.search_placeholder.resource.expense'))
            ->columns(static::getExpenseCardColumns())
            ->contentGrid(static::getExpenseCardContentGrid())
            ->filters(\App\Filament\Resources\Base\BaseResource::getExpenseTableFilters(true))->filtersFormWidth(Width::Small)
            ->defaultGroup(
                TableGroup::make('date')
                    ->getTitleFromRecordUsing(function (Expense $record): string {
                        $monthName = mb_convert_case(
                            $record->date->locale('ru')->translatedFormat('F Y'),
                            MB_CASE_TITLE,
                            'UTF-8'
                        );

                        return $monthName;
                    })
                    ->orderQueryUsing(
                        fn (Builder $query) => $query->orderBy('date', 'desc')
                    )
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
            )
            ->actions([
                \Filament\Actions\Action::make('select')
                    ->label(__('resources.buttons.select'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (Expense $record) {
                        $this->dispatch('expense:selected', expenseId: $record->id);
                        $this->js('close()');
                    })
                    ->extraAttributes(['class' => 'ml-auto']),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->poll('30s');
    }
}
