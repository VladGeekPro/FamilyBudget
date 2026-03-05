<?php

namespace App\Filament\Widgets;

use App\Filament\Tables\Concerns\HasExpenseCardTableLayout;
use App\Filament\Widgets\Concerns\InteractsWithExpenseFilters;
use App\Models\Expense;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpensesTableWidget extends BaseWidget
{
    use HasExpenseCardTableLayout;
    use InteractsWithExpenseFilters;
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Затраты по выбранным фильтрам')
            ->description('Карточки расходов с группировкой по месяцам.')
            ->query(
                $this->expenseQuery()
                    ->with(['user:id,name,image', 'category:id,name', 'supplier:id,name,image'])
            )
            ->defaultSort('date', 'desc')
            ->columns(static::getExpenseCardColumns())
            ->contentGrid(static::getExpenseCardContentGrid())
            ->recordClasses('expense-record')
            ->defaultGroup(
                TableGroup::make('date')
                    ->getTitleFromRecordUsing(function (Expense $record): string {
                        $monthStart = $record->date->copy()->startOfMonth()->toDateString();
                        $monthEnd = $record->date->copy()->endOfMonth()->toDateString();

                        $sum = $this->expenseQuery()
                            ->whereBetween('date', [$monthStart, $monthEnd])
                            ->sum('sum');

                        $monthName = mb_convert_case(
                            $record->date->locale('ru')->translatedFormat('F Y'),
                            MB_CASE_TITLE,
                            'UTF-8',
                        );

                        return "{$monthName} - " . number_format((float) $sum, 2, ',', ' ') . ' MDL';
                    })
                    ->orderQueryUsing(fn (Builder $query) => $query->orderBy('date', 'desc'))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
            )
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10);
    }
}
