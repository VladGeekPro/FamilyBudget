<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Filament\Resources\Base\BaseResource;

use Filament\Schemas\Components\Group;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid as FormGrid;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Filters\SelectFilter;

use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

use Illuminate\Support\Str;
use Filament\Support\Enums\FontWeight;

use \Illuminate\Database\Eloquent\Model;

class ExpenseResource extends BaseResource
{
    protected static ?string $model = Expense::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Транзакции';

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
                'label' => __('resources.buttons.expense_change_request_view'),
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
                    ->visible(fn(Expense $record) => static::getChangeRequestMeta($record) !== null)
                    ->label(function (Expense $record) {
                        $meta = static::getChangeRequestMeta($record);

                        return $meta['label'] ?? '';
                    })
                    ->icon(function (Expense $record) {
                        $meta = static::getChangeRequestMeta($record);

                        return $meta['icon'] ?? null;
                    })
                    ->url(function (Expense $record) {
                        $meta = static::getChangeRequestMeta($record);

                        return $meta['url'] ?? null;
                    }),

                \Filament\Actions\ViewAction::make()
                    ->visible(fn($record) => $record->date->isBefore(now()->startOfMonth()))
                    ->extraAttributes(['class' => 'ml-auto']),
            ],
            parent::getTableActions()
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([

                Section::make(__('resources.sections.main'))
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->schema([

                        Group::make(
                            static::getExpenseFormFields()
                        )

                    ])
            ]);
    }

    public static function table(Table $table): Table
    {

        $table = parent::table($table);

        return $table

            ->modifyQueryUsing(fn($query) => $query->with('pendingChangeRequest'))
            ->columns([
                TableGrid::make([
                    'default' => 2
                ])
                    ->schema([
                        TextColumn::make('date')
                            ->label(__('resources.fields.date'))
                            ->dateTime('d M. Y')
                            ->color('info')
                            ->columnSpan(1),
                        ImageColumn::make('user.image')
                            ->circular()
                            ->height(40)
                            ->width(40)
                            ->extraAttributes(['style' => 'margin-left:auto;']),
                    ]),
                Split::make([
                    TableGrid::make()
                        ->columns(1)
                        ->schema([
                            ImageColumn::make('supplier.image')
                                ->circular()
                                ->height(100)
                                ->width(100)
                        ])->grow(false),
                    Stack::make([
                        TableGrid::make([
                            'default' => 3
                        ])
                            ->schema([
                                TextColumn::make('supplier.name')
                                    ->label(__('resources.fields.name.animate'))
                                    ->size('md')
                                    ->weight(FontWeight::Bold)
                                    ->searchable()
                                    ->columnSpan(2),
                                TextColumn::make('sum')
                                    ->numeric(decimalPlaces: 2)
                                    ->color('warning')
                                    ->money('MDL')
                                    ->extraAttributes(['class' => 'justify-end']),
                            ])->grow(),

                        TextColumn::make('notes')
                            ->label(__('resources.fields.notes'))
                            ->html()
                            ->formatStateUsing(fn($state) => Str::markdown($state))
                            ->searchable()
                            ->color("gray")
                            ->limit(100)
                            ->toggleable(),
                    ])
                ])->extraAttributes(['class' => 'py-2'])
            ])->contentGrid([
                'md' => 2,
                'lg' => 1,
                'xl' => 2,
                '2xl' => 3,
            ])

            ->filters(static::getExpenseTableFilters(true))
            ->recordClasses('expense-record')
            ->defaultGroup(
                TableGroup::make('date')
                    ->getTitleFromRecordUsing(function (Expense $record): string {

                        $filters = session('tableFilters', []);

                        $filteredQuery = Expense::query();

                        if (!empty($filters['user'])) {
                            $filteredQuery->whereIn('expenses.user_id', $filters['user']);
                        }

                        if (!empty($filters['category'])) {
                            $filteredQuery->whereIn('expenses.category_id', $filters['category']);
                        }

                        if (!empty($filters['supplier'])) {
                            $filteredQuery->whereIn('expenses.supplier_id', $filters['supplier']);
                        }

                        if (!empty($filters['date']['date_from'])) {
                            $filteredQuery->whereDate('expenses.date', '>=', $filters['date']['date_from']);
                        }

                        if (!empty($filters['date']['date_until'])) {
                            $filteredQuery->whereDate('expenses.date', '<=', $filters['date']['date_until']);
                        }

                        if (!empty($filters['sum']['sum_min'])) {
                            $filteredQuery->where('expenses.sum', '>=', $filters['sum']['sum_min']);
                        }

                        if (!empty($filters['sum']['sum_max'])) {
                            $filteredQuery->where('expenses.sum', '<=', $filters['sum']['sum_max']);
                        }

                        if (!empty($filters['search'])) {
                            $search = $filters['search'];
                            $filteredQuery
                                ->leftJoin('suppliers', 'expenses.supplier_id', '=', 'suppliers.id')
                                ->where(function ($query) use ($search) {
                                    $query->where('expenses.notes', 'like', "%{$search}%")
                                        ->orWhere('suppliers.name', 'like', "%{$search}%");
                                });
                        }

                        $month = $record->date->format('Y');
                        $year  = $record->date->format('m');

                        $filteredQuery->whereYear('expenses.date', $month)->whereMonth('expenses.date', $year);

                        $sum = $filteredQuery->sum('expenses.sum');

                        $monthName = mb_convert_case(
                            $record->date->locale('ru')->translatedFormat('F Y'),
                            MB_CASE_TITLE,
                            'UTF-8'
                        );

                        return "{$monthName} — " . number_format($sum, 2, ',', ' ') . " MDL";
                    })
                    ->orderQueryUsing(
                        fn(Builder $query) => $query->orderBy('date', 'desc')
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
