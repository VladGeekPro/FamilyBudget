<?php

// namespace App\Filament\Resources;

// use App\Filament\Resources\DebtResource\Pages;
// use App\Filament\Resources\DebtResource\RelationManagers;
// use App\Models\Debt;
// use Filament\Forms;
// use Filament\Forms\Form;
// use Filament\Resources\Resource;
// use Filament\Tables;
// use Filament\Tables\Table;
// use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope;

// class DebtResource extends Resource
// {
//     protected static ?string $model = Debt::class;

//     protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

//     public static function form(Form $form): Form
//     {
//         return $form
//             ->schema([
//                 Forms\Components\DatePicker::make('date')
//                     ->required(),
//                 Forms\Components\TextInput::make('user_id')
//                     ->required()
//                     ->numeric(),
//                 Forms\Components\TextInput::make('sum')
//                     ->required()
//                     ->numeric(),
//                 Forms\Components\Toggle::make('paid')
//                     ->required(),
//                 Forms\Components\Textarea::make('notes')
//                     ->columnSpanFull(),
//                 Forms\Components\DatePicker::make('date_paid'),
//             ]);
//     }

//     public static function table(Table $table): Table
//     {
//         return $table
//             ->columns([
//                 Tables\Columns\TextColumn::make('date')
//                     ->date()
//                     ->sortable(),
//                 Tables\Columns\TextColumn::make('user_id')
//                     ->numeric()
//                     ->sortable(),
//                 Tables\Columns\TextColumn::make('sum')
//                     ->numeric()
//                     ->sortable(),
//                 Tables\Columns\IconColumn::make('paid')
//                     ->boolean(),
//                 Tables\Columns\TextColumn::make('date_paid')
//                     ->date()
//                     ->sortable(),
//                 Tables\Columns\TextColumn::make('created_at')
//                     ->dateTime()
//                     ->sortable()
//                     ->toggleable(isToggledHiddenByDefault: true),
//                 Tables\Columns\TextColumn::make('updated_at')
//                     ->dateTime()
//                     ->sortable()
//                     ->toggleable(isToggledHiddenByDefault: true),
//             ])
//             ->filters([
//                 //
//             ])
//             ->actions([
//                 Tables\Actions\EditAction::make(),
//             ])
//             ->bulkActions([
//                 Tables\Actions\BulkActionGroup::make([
//                     Tables\Actions\DeleteBulkAction::make(),
//                 ]),
//             ]);
//     }

//     public static function getRelations(): array
//     {
//         return [
//             //
//         ];
//     }

//     public static function getPages(): array
//     {
//         return [
//             'index' => Pages\ListDebts::route('/'),
//             'create' => Pages\CreateDebt::route('/create'),
//             'edit' => Pages\EditDebt::route('/{record}/edit'),
//         ];
//     }
// }


namespace App\Filament\Resources;

use App\Filament\Resources\DebtResource\Pages;
use App\Filament\Resources\DebtResource\RelationManagers;
use App\Models\Debt;
use App\Models\User;
use App\Filament\Resources\Base\BaseResource;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid as FormGrid;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;

class DebtResource extends BaseResource
{
    protected static ?string $model = Debt::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'задолженность';

    protected static ?string $pluralModelLabel = 'Задолженности';

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static ?string $defaultSortColumn = 'date';

    protected static string $defaultSortDirection = 'desc';

    public static function getNavigationBadge(): ?string
    {
        $unpaidCount = Debt::unpaid()->count();
        $unpaidSum = Debt::unpaid()->sum('sum');

        if ($unpaidCount === 0) {
            return null;
        }

        return "{$unpaidCount} ({$unpaidSum} MDL)";
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('resources.sections.debt.main'))
                    ->icon('heroicon-o-exclamation-circle')
                    ->iconColor('warning')
                    ->schema([
                        self::getDebtInfoSection(),
                        self::getPaymentStatusSection(),
                    ]),
            ]);
    }

    /**
     * Основной раздел информации о задолженности
     */
    private static function getDebtInfoSection(): Group
    {
        return Group::make([
            FormGrid::make([
                'default' => 1,
                'sm' => 2,
            ])
                ->schema([
                    Select::make('user_id')
                        ->label(__('resources.fields.debtor'))
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
                        ->columnSpanFull(),

                    DatePicker::make('date')
                        ->label(__('resources.fields.date'))
                        ->required()
                        ->default(now())
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                        ]),

                    TextInput::make('sum')
                        ->label(__('resources.fields.sum'))
                        ->required()
                        ->suffix('MDL')
                        // ->numeric(decimalPlaces: 2)
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                        ]),

                    Textarea::make('notes')
                        ->label(__('resources.fields.notes'))
                        ->placeholder('Описание задолженности...')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Раздел статуса оплаты
     */
    private static function getPaymentStatusSection(): Section
    {
        return Section::make('Статус оплаты')
            ->icon('heroicon-o-check-badge')
            ->iconColor('success')
            ->collapsible()
            ->schema([
                Group::make([
                    Toggle::make('paid')
                        ->label('Оплачено')
                        ->live(onBlur: true)
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                        ]),

                    DatePicker::make('date_paid')
                        ->label('Дата оплаты')
                        ->visible(fn($get) => $get('paid'))
                        ->required(fn($get) => $get('paid'))
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                        ]),
                ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
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
                            ImageColumn::make('user.image')
                                ->circular()
                                ->height(100)
                                ->width(100)
                        ])->grow(false),
                    Stack::make([
                        TableGrid::make([
                            'default' => 2
                        ])
                            ->schema([
                                TextColumn::make('user.name')
                                    ->label(__('resources.fields.debtor'))
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
                            ->color('gray')
                            ->limit(100)
                            ->toggleable(isToggledHiddenByDefault: true),

                        TableGrid::make([
                            'default' => 2
                        ])
                            ->schema([
                                IconColumn::make('paid')
                                    ->label('Статус')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                                TextColumn::make('date_paid')
                                    ->label('Дата оплаты')
                                    ->dateTime('d M. Y')
                                    ->color('success')
                                    ->icon('heroicon-o-calendar')
                                    // ->visible(fn($record) => $record->paid),
                            ])->grow(),
                    ])
                ])->extraAttributes(['class' => 'py-2'])
            ])->contentGrid([
                'md' => 2,
                'lg' => 1,
                'xl' => 2,
                '2xl' => 3,
            ])

            ->filters([
                Filter::make('paid')
                    ->label('Оплачено')
                    ->query(fn(Builder $query) => $query->where('paid', true)),
                Filter::make('unpaid')
                    ->label('Не оплачено')
                    ->query(fn(Builder $query) => $query->where('paid', false)),

                SelectFilter::make('user')
                    ->label(__('resources.fields.user'))
                    ->relationship('user', 'name')
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
                        if (!$data['date_from'] && !$data['date_until']) {
                            return null;
                        } elseif ($data['date_from'] && !$data['date_until']) {
                            return 'С ' . Carbon::parse($data['date_from'])->translatedFormat('d F Y');
                        } elseif (!$data['date_from'] && $data['date_until']) {
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

                        if (!$min && !$max) {
                            return null;
                        } elseif ($min && !$max) {
                            return 'С ' . number_format($min, 2, ',', ' ') . ' MDL';
                        } elseif (!$min && $max) {
                            return 'До ' . number_format($max, 2, ',', ' ') . ' MDL';
                        } else {
                            return 'Интервал суммы: ' . number_format($min, 2, ',', ' ') . ' – ' . number_format($max, 2, ',', ' ') . ' MDL';
                        }
                    }),
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
            'index' => Pages\ListDebts::route('/'),
            'create' => Pages\CreateDebt::route('/create'),
            'edit' => Pages\EditDebt::route('/{record}/edit'),
        ];
    }
}