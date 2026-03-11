<?php

namespace App\Filament\Widgets;

use App\Filament\Traits\HasExpenseCardTableLayout;
use App\Filament\Traits\InteractsWithExpenseFilters;
use App\Models\Expense;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExpensesTableWidget extends BaseWidget
{
    use HasExpenseCardTableLayout;
    use InteractsWithExpenseFilters;
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.expenses-table-widget';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?array $cachedMonthlySums = null;

    public function getViewData(): array
    {
        $today = now();
        $daysInMonth = (int) $today->daysInMonth;
        $daysElapsed = (int) $today->day;
        $daysRemaining = $daysInMonth - $daysElapsed;
        $monthProgress = $daysInMonth > 0 ? round(($daysElapsed / $daysInMonth) * 100) : 0;

        $baseQuery = $this->expenseQuery();

        $totalExpenses = (clone $baseQuery)->sum('sum');
        $totalCount = (int) (clone $baseQuery)->count();

        // Top 3 categories
        $topCategories = $this->expenseQuery()
            ->selectRaw('category_id, SUM(sum) as total, COUNT(*) as cnt')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('category:id,name')
            ->get()
            ->map(fn($row) => (object) [
                'name'  => $row->category?->name ?? 'Без категории',
                'total' => $row->total,
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
                        $sum = $this->getMonthlySums($record->date->format('Y-m'));

                        $monthName = mb_convert_case(
                            $record->date->locale('ru')->translatedFormat('F Y'),
                            MB_CASE_TITLE,
                            'UTF-8',
                        );

                        return "{$monthName} - " . number_format($sum, 2, ',', ' ') . ' MDL';
                    })
                    ->orderQueryUsing(fn(Builder $query) => $query->orderBy('date', 'desc'))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
            )
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    private function getMonthlySums(string $monthKey): array|float
    {
        $this->cachedMonthlySums ??= $this->loadMonthlySums();
        return $this->cachedMonthlySums[$monthKey];
    }

    private function loadMonthlySums(): array
    {
        return $this->expenseQuery()
            ->selectRaw("strftime('%Y-%m', date) as month_key, SUM(sum) as total")
            ->groupBy('month_key')
            ->get()
            ->mapWithKeys(fn($row) => [(string) $row->month_key => $row->total])
            ->all();
    }
}
