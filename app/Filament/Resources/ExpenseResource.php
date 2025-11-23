<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Filament\Resources\Base\BaseResource;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid as FormGrid;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Filters\SelectFilter;

use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

use Illuminate\Support\Str;
use Filament\Support\Enums\FontWeight;

use Filament\Notifications\Notification;

class ExpenseResource extends BaseResource
{
    protected static ?string $model = Expense::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'затрату';

    protected static ?string $pluralModelLabel = 'Затраты';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $defaultSortColumn = null;

    public static function getNavigationBadge(): ?string
    {

        $vladCurrentMonthExpenses = Expense::whereHas('user', function ($query) {
            $query->where('email', 'vladret0@gmail.com');
        })
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->count();

        // $oleaCurrentMonthExpenses = Expense::whereHas('user', function ($query) {
        //     $query->where('email', 'vladret0@gmail.com');
        // })
        //     ->whereMonth('date', now()->month)
        //     ->whereYear('date', now()->year)
        //     ->count();

        $oleaCurrentMonthExpenses = 0;

        return "{$oleaCurrentMonthExpenses} 😇 {$vladCurrentMonthExpenses} 😎";
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make(__('resources.sections.expense.main'))
                    ->icon('heroicon-o-shopping-bag')
                    ->iconColor('warning')
                    ->schema([

                        FormGrid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([

                                Group::make([
                                    Select::make('user_id')
                                        ->label(__('resources.fields.payer'))
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn($livewire) => $livewire->validateOnly('data.user_id'))
                                        ->allowHtml()
                                        ->options(fn() => User::all()->mapWithKeys(function ($user) {
                                            return [$user->getKey() => static::getCleanOptionString($user)];
                                        })->toArray())
                                        ->getSearchResultsUsing(function (string $search) {
                                            $users = User::where('name', 'like', "%{$search}%")->limit(50)->get();
                                            return $users->mapWithKeys(function ($user) {
                                                return [$user->getKey() => static::getCleanOptionString($user)];
                                            })->toArray();
                                        })
                                        ->getOptionLabelUsing(function ($value): string {
                                            $user = User::find($value);
                                            return static::getCleanOptionString($user);
                                        })
                                        ->optionsLimit(10)
                                        ->searchable()
                                        ->preload()
                                        ->selectablePlaceholder(false)
                                        ->loadingMessage(__('resources.notifications.load.users'))
                                        ->noSearchResultsMessage(__('resources.notifications.skip.users'))
                                        ->default(auth()->user()->id)
                                        ->columnSpanFull(),


                                    DatePicker::make('date')
                                        ->label(__('resources.fields.date'))
                                        ->required()
                                        ->default(now()),

                                    Select::make('category_id')
                                        ->label(__('resources.fields.category'))
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($livewire, $set, $state, $get) {
                                            $supplierId = $get('supplier_id');

                                            if (filled($supplierId)) {
                                                $supplier = Supplier::find($supplierId);

                                                if (blank($state) || ($supplier && $supplier->category_id !== $state)) {
                                                    $set('supplier_id', null);

                                                    Notification::make()
                                                        ->title(__('resources.notifications.warn.expense.title'))
                                                        ->body(__('resources.notifications.warn.expense.body'))
                                                        ->warning()
                                                        ->color('warning')
                                                        ->duration(5000)
                                                        ->send();
                                                }

                                                $livewire->validate([
                                                    'data.supplier_id' => 'required',
                                                    'data.category_id' => 'required'
                                                ]);
                                            }

                                            $livewire->validateOnly('data.category_id');
                                        })
                                        ->allowHtml()
                                        ->options(fn() => Category::all()->mapWithKeys(function ($category) {
                                            return [$category->getKey() => static::getCleanOptionString($category)];
                                        })->toArray())
                                        ->getSearchResultsUsing(function (string $search) {
                                            $categories = Category::where('name', 'like', "%{$search}%")->limit(50)->get();
                                            return $categories->mapWithKeys(function ($category) {
                                                return [$category->getKey() => static::getCleanOptionString($category)];
                                            })->toArray();
                                        })
                                        ->getOptionLabelUsing(function ($value): string {
                                            $category = Category::find($value);
                                            return static::getCleanOptionString($category);
                                        })
                                        ->optionsLimit(10)
                                        ->searchable()
                                        ->preload()
                                        ->loadingMessage(__('resources.notifications.load.categories'))
                                        ->noSearchResultsMessage(__('resources.notifications.skip.categories'))
                                        ->columnSpanFull(),

                                    Select::make('supplier_id')
                                        ->label(__('resources.fields.supplier'))
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($livewire, $set, $state, $get) {
                                            $livewire->validateOnly('data.supplier_id');
                                            if (filled($state) && empty($get('category_id'))) {
                                                $set('category_id', Supplier::find($state)->category_id);
                                                $livewire->validateOnly('data.category_id');
                                            }
                                        })
                                        ->allowHtml()
                                        ->options(function ($get) {
                                            $query = Supplier::query();

                                            if (filled($get('category_id'))) {
                                                $query->where('category_id', $get('category_id'));
                                            }

                                            return $query->get()->mapWithKeys(function ($supplier) {
                                                return [$supplier->getKey() => static::getCleanOptionString($supplier)];
                                            })->toArray();
                                        })
                                        ->getSearchResultsUsing(function (string $search, $get) {
                                            $query = Supplier::query()->where('name', 'like', "%{$search}%");

                                            if (filled($get('category_id'))) {
                                                $query->where('category_id', $get('category_id'));
                                            }

                                            return $query->limit(50)->get()->mapWithKeys(function ($supplier) {
                                                return [$supplier->getKey() => static::getCleanOptionString($supplier)];
                                            })->toArray();
                                        })
                                        ->getOptionLabelUsing(function ($value): string {
                                            $supplier = Supplier::find($value);
                                            return static::getCleanOptionString($supplier);
                                        })
                                        ->optionsLimit(10)
                                        ->searchable()
                                        ->preload()
                                        ->selectablePlaceholder(false)
                                        ->loadingMessage(__('resources.notifications.load.suppliers'))
                                        ->noSearchResultsMessage(__('resources.notifications.skip.suppliers'))
                                        ->columnSpanFull(),

                                    TextInput::make('sum')
                                        ->label(__('resources.fields.sum'))
                                        ->required()
                                        ->suffix('MDL')
                                        ->numeric(),
                                ])
                                    ->columnSpanFull(),


                                MarkdownEditor::make('notes')
                                    ->label(__('resources.fields.notes'))
                                    ->disableToolbarButtons([
                                        'attachFiles',
                                        'blockquote',
                                        'codeBlock',
                                        'heading',
                                        'table',
                                        'link',
                                    ])
                                    ->columnSpanFull(),

                            ]),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {

        $table = parent::table($table);

        return $table

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
                            'default' => 2
                        ])
                            ->schema([
                                TextColumn::make('supplier.name')
                                    ->label(__('resources.fields.name.animate'))
                                    ->size(TextColumnSize::Medium)
                                    ->weight(FontWeight::Bold)
                                    ->searchable()
                                    ->columnSpan(1),
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

            ->filters([

                SelectFilter::make('user')
                    ->label(__('resources.fields.user'))
                    ->relationship('user', 'name')
                    ->multiple()
                    ->preload()
                    ->placeholder(''),
                SelectFilter::make('category')
                    ->label(__('resources.fields.category'))
                    ->relationship('category', 'name')
                    ->multiple()
                    ->preload()
                    ->placeholder(''),
                SelectFilter::make('supplier')
                    ->label(__('resources.fields.supplier'))
                    ->relationship('supplier', 'name')
                    ->multiple()
                    ->preload()
                    ->placeholder(''),
                Filter::make('date')
                    ->label(__('resources.fields.date'))
                    ->form([
                        DatePicker::make("date_from")->label(__('resources.filters.date_from')),
                        DatePicker::make("date_until")->label(__('resources.filters.date_until')),
                    ])->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['date_from'] && ! $data['date_until']) {
                            return null;
                        } elseif ($data['date_from'] && ! $data['date_until']) {
                            return 'С ' . Carbon::parse($data['date_from'])->translatedFormat('d F Y');
                        } elseif (! $data['date_from'] && $data['date_until']) {
                            return 'До ' . Carbon::parse($data['date_until'])->translatedFormat('d F Y');
                        } else {
                            return 'Период: ' . Carbon::parse($data['date_from'])->translatedFormat('d F Y') . ' – ' . Carbon::parse($data['date_until'])->translatedFormat('d F Y');
                        }
                    }),
                Filter::make('sum')
                    ->label(__('resources.fields.sum'))
                    ->form([
                        TextInput::make('sum_min')
                            ->numeric()
                            ->label(__('resources.filters.sum_min')),
                        TextInput::make('sum_max')
                            ->numeric()
                            ->label(__('resources.filters.sum_max')),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['sum_min'], fn(Builder $query, $min) => $query->where('sum', '>=', $min))
                            ->when($data['sum_max'], fn(Builder $query, $max) => $query->where('sum', '<=', $max));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $min = $data['sum_min'] ?? null;
                        $max = $data['sum_max'] ?? null;

                        if (! $min && ! $max) {
                            return null;
                        } elseif ($min && ! $max) {
                            return 'С ' . number_format($min, 2, ',', ' ') . ' MDL';
                        } elseif (! $min && $max) {
                            return 'До ' . number_format($max, 2, ',', ' ') . ' MDL';
                        } else {
                            return 'Интервал суммы: ' . number_format($min, 2, ',', ' ') . ' – ' . number_format($max, 2, ',', ' ') . ' MDL';
                        }
                    }),
            ])
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
            ->actions([
                Action::make('copy')
                    ->label(__('resources.buttons.copy'))
                    ->color('info')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $data = array_merge(
                            $record->only(['user_id', 'category_id', 'supplier_id', 'sum', 'notes']),
                            ['date' => optional($record->date)->format('Y-m-d')]
                        );
                        return redirect()->route('filament.admin.resources.expenses.create', ['data' => $data]);
                    })->extraAttributes(['style' => 'margin-right: auto;']),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

            ])
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
        ];
    }
}
