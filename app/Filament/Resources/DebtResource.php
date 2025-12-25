<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DebtResource\Pages;
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
use Filament\Tables\Columns\ToggleColumn;
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

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $defaultSortColumn = 'date';

    protected static string $defaultSortDirection = 'desc';

    public static function getNavigationBadge(): ?string
    {
        $unpaidRecords = Debt::unpaid()->get();

        if ($unpaidRecords->isEmpty()) {
            return null;
        }

        $badgeMessage = "";
        foreach ($unpaidRecords as $record) {
            $icon = User::getIcon($record->user_email);
            $badgeMessage .= "{$record->unpaid_count} {$icon}";
        }

        return trim($badgeMessage);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected static function getTableActions(): array
    {
        return [];
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
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    DatePicker::make('date')
                        ->label(__('resources.fields.date'))
                        ->required()
                        ->default(now())
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                        ]),

                    TextInput::make('sum')
                        ->label(__('resources.fields.sum'))
                        ->required()
                        ->suffix('MDL')
                        // ->numeric(decimalPlaces: 2)
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                        ]),

                    Textarea::make('notes')
                        ->label(__('resources.fields.notes'))
                        ->placeholder('Описание задолженности...')
                        ->rows(3)
                        ->disabled()
                        ->dehydrated(false)
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
                        ->onIcon('heroicon-m-bolt')
                        ->offIcon('heroicon-m-user')
                        ->onColor('success')
                        ->offColor('danger')
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
                    'default' => 3
                ])
                    ->schema([
                        TextColumn::make('date')
                            ->label(__('resources.fields.date'))
                            ->dateTime('d M. Y')
                            ->color('info')
                            ->columnSpan(1),

                        Split::make([
                            ImageColumn::make('overpayment.user.image')
                                ->circular()
                                ->height(40)
                                ->width(40)
                                ->extraAttributes(['style' => 'margin-left:auto;']),
                            Stack::make([
                                TextColumn::make('')
                                    ->state(__('resources.fields.overpayment'))
                                    ->color('primary')
                                    ->weight(FontWeight::Bold),
                                TextColumn::make('overpayment.sum')
                                    ->label('')
                                    ->numeric(decimalPlaces: 2)
                                    ->money('MDL')
                            ])->grow(false),
                        ])->columnSpan(2),
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
                                TextColumn::make('date_paid')
                                    ->label('Дата оплаты')
                                    ->dateTime('d M. Y')
                                    ->color('success')
                                    ->icon('heroicon-o-calendar')
                                    ->visible(fn($record) => $record && $record->paid),
                            ])->grow()
                            ->visible(fn($record) => $record && $record->user_id),

                        TableGrid::make([
                            'default' => 2
                        ])
                            ->schema([
                                TextColumn::make('sum')
                                    ->numeric(decimalPlaces: 2)
                                    ->color('warning')
                                    ->money('MDL'),

                                ToggleColumn::make('paid')
                                    ->label('Статус')
                                    ->onColor('success')
                                    ->offColor('danger')
                                    ->onIcon('heroicon-o-check')
                                    ->offIcon('heroicon-o-x-mark')
                                    ->updateStateUsing(function ($record, $state) {
                                        $record->update(['paid' => $state]);
                                        $record->update(['date_paid' => now()]);
                                    }),
                            ])->grow()
                            ->visible(fn($record) => $record && $record->user_id),

                        TextColumn::make('notes')
                            ->label(__('resources.fields.notes'))
                            ->color('gray')
                            ->toggleable(isToggledHiddenByDefault: false)
                            ->visible(fn($record) => $record && $record->user_id),

                    ])
                ])->extraAttributes(['class' => 'py-2']),

                TextColumn::make('notes')
                    ->label(__('resources.fields.notes'))
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: false),

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
        ];
    }
}
