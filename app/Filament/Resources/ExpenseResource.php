<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Base\BaseResource;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Tables\Concerns\HasExpenseCardTableLayout;
use App\Models\Expense;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends BaseResource
{
    use HasExpenseCardTableLayout;

    protected static ?string $model = Expense::class;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Транзакции';
    }

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Затраты';

    protected static ?string $modelLabel = 'затрату';

    protected static ?string $pluralModelLabel = 'затраты';

    protected static ?string $defaultSortColumn = null;

    public static function getChangeRequestMeta(Expense $expense): ?array
    {
        if (! $expense->canRequestChange()) {
            return null;
        }

        $expense->loadMissing('pendingChangeRequest');
        $pending = $expense->pendingChangeRequest;

        if ($pending) {
            return [
                'name' => 'expense_change_request_view',
                'label' => __('resources.buttons.view'),
                'icon' => 'heroicon-o-eye',
                'url' => ExpenseChangeRequestResource::getUrl('view', ['record' => $pending->id]),
            ];
        }

        return [
            'name' => 'expense_change_request_create',
            'label' => __('resources.buttons.expense_change_request'),
            'icon' => 'heroicon-o-pencil-square',
            'url' => ExpenseChangeRequestResource::getUrl('create', [
                'data' => ['expense_id' => $expense->id, 'action_type' => 'edit'],
            ]),
        ];
    }

    protected static function getTableActions(): array
    {
        $isCurrentMonthOrLater = fn (Expense $record): bool => $record->date->gte(now()->startOfMonth());

        $parentActions = array_map(
            fn ($action) => $action->visible($isCurrentMonthOrLater),
            parent::getTableActions()
        );

        return array_merge(
            [
                Action::make('copy')
                    ->label(__('resources.buttons.copy'))
                    ->color('info')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $data = array_merge(
                            $record->only(['user_id', 'category_id', 'supplier_id', 'notes']),
                            ['date' => now()->format('Y-m-d')]
                        );

                        return redirect()->route('filament.admin.resources.expenses.create', ['data' => $data]);
                    }),

                Action::make('expense_change_request')
                    ->visible(fn (Expense $record) => static::getChangeRequestMeta($record) !== null)
                    ->label(fn (Expense $record) => static::getChangeRequestMeta($record)['label'] ?? '')
                    ->icon(fn (Expense $record) => static::getChangeRequestMeta($record)['icon'] ?? null)
                    ->url(fn (Expense $record) => static::getChangeRequestMeta($record)['url'] ?? null),

                \Filament\Actions\ViewAction::make()
                    ->visible(fn (Expense $record) => $record->date->lt(now()->startOfMonth()))
                    ->extraAttributes(['class' => 'ml-auto']),
            ],
            $parentActions,
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('resources.sections.main'))
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->columnSpanFull()
                    ->schema([
                        Group::make(
                            static::getExpenseFormFields()
                        ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('pendingChangeRequest'))
            ->columns(static::getExpenseCardColumns())
            ->contentGrid(static::getExpenseCardContentGrid())
            ->filters(static::getExpenseTableFilters())
            ->recordClasses('expense-record')
            ->defaultGroup(
                TableGroup::make('date')
                    ->getTitleFromRecordUsing(function (Expense $record): string {
                        $filters = session('tableFilters', []);

                        $filteredQuery = Expense::query();

                        if (! empty($filters['user'])) {
                            $filteredQuery->whereIn('expenses.user_id', $filters['user']);
                        }

                        if (! empty($filters['category'])) {
                            $filteredQuery->whereIn('expenses.category_id', $filters['category']);
                        }

                        if (! empty($filters['supplier'])) {
                            $filteredQuery->whereIn('expenses.supplier_id', $filters['supplier']);
                        }

                        if (! empty($filters['date']['date_from'])) {
                            $filteredQuery->whereDate('expenses.date', '>=', $filters['date']['date_from']);
                        }

                        if (! empty($filters['date']['date_until'])) {
                            $filteredQuery->whereDate('expenses.date', '<=', $filters['date']['date_until']);
                        }

                        if (! empty($filters['sum']['sum_min'])) {
                            $filteredQuery->where('expenses.sum', '>=', $filters['sum']['sum_min']);
                        }

                        if (! empty($filters['sum']['sum_max'])) {
                            $filteredQuery->where('expenses.sum', '<=', $filters['sum']['sum_max']);
                        }

                        if (! empty($filters['search'])) {
                            $search = $filters['search'];
                            $filteredQuery
                                ->leftJoin('suppliers', 'expenses.supplier_id', '=', 'suppliers.id')
                                ->where(function ($query) use ($search) {
                                    $query->where('expenses.notes', 'like', "%{$search}%")
                                        ->orWhere('suppliers.name', 'like', "%{$search}%");
                                });
                        }

                        $month = $record->date->format('Y');
                        $year = $record->date->format('m');

                        $filteredQuery->whereYear('expenses.date', $month)->whereMonth('expenses.date', $year);

                        $sum = $filteredQuery->sum('expenses.sum');

                        $monthName = mb_convert_case(
                            $record->date->locale('ru')->translatedFormat('F Y'),
                            MB_CASE_TITLE,
                            'UTF-8'
                        );

                        return "{$monthName} - " . number_format((float) $sum, 2, ',', ' ') . ' MDL';
                    })
                    ->orderQueryUsing(
                        fn (Builder $query) => $query->orderBy('date', 'desc')
                    )
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
            )
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
            'view' => Pages\ViewExpense::route('/{record}'),
        ];
    }
}
