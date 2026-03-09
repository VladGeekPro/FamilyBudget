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

    protected string $view = 'filament.widgets.expenses-table-widget';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $today = now();
        $daysInMonth = (int) $today->daysInMonth;
        $daysElapsed = (int) $today->day;
        $daysRemaining = $daysInMonth - $daysElapsed;
        $monthProgress = $daysInMonth > 0 ? round(($daysElapsed / $daysInMonth) * 100) : 0;

        $baseQuery = $this->expenseQuery();

        $totalExpenses = (float) (clone $baseQuery)->sum('sum');
        $totalCount = (int) (clone $baseQuery)->count();

        // Top 3 categories
        $topCategories = $this->expenseQuery()
            ->selectRaw('category_id, COALESCE(SUM(sum),0) as total, COUNT(*) as cnt')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('category:id,name')
            ->get()
            ->map(fn($row) => (object) [
                'name'  => $row->category?->name ?? 'Без категории',
                'total' => (float) $row->total,
                'count' => (int) $row->cnt,
            ]);

        return [
            'totalExpenses'   => $totalExpenses,
            'totalCount'      => $totalCount,
            'topCategories'   => $topCategories,
            'daysElapsed'     => $daysElapsed,
            'daysInMonth'     => $daysInMonth,
            'daysRemaining'   => $daysRemaining,
            'monthProgress'   => $monthProgress,
            'monthLabel'      => now()->translatedFormat('F Y'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(
                $this->expenseQuery()
                    ->with(['user:id,name,image', 'category:id,name', 'supplier:id,name,image'])
            )
            ->searchable(false)
            ->searchPlaceHolder(__('resources.search_placeholder.resource.expense'))
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
                            ->whereDate('date', '>=', $monthStart)
                            ->whereDate('date', '<=', $monthEnd)
                            ->sum('sum');

                        $monthName = mb_convert_case(
                            $record->date->locale('ru')->translatedFormat('F Y'),
                            MB_CASE_TITLE,
                            'UTF-8',
                        );

                        return "{$monthName} - " . number_format((float) $sum, 2, ',', ' ') . ' MDL';
                    })
                    ->orderQueryUsing(fn(Builder $query) => $query->orderBy('date', 'desc'))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
            )
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
