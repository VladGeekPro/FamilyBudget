<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DebtResource\Pages;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Overpayment;
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
use Filament\Forms\Components\ToggleButtons;
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
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;

use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;

class DebtResource extends BaseResource
{
    protected static ?string $model = Debt::class;

    protected static ?string $navigationGroup = 'Транзакции';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationLabel = 'Задолженности';

    protected static ?string $modelLabel = 'задолженность';

    protected static ?string $pluralModelLabel = 'задолженности';

    protected static ?string $defaultSortColumn = 'date';

    protected static string $defaultSortDirection = 'desc';

    protected static function updateDebtNotes(callable $get, callable $set, ?float $paidAmount = null): void
    {
        $debtSum = $get('debt_sum') ?? 0;
        $paymentStatus = $get('payment_status');
        $userId = $get('user_id');

        if (!$userId || !$paymentStatus) {
            return;
        }

        $difference = $paidAmount !== null
            ? $debtSum - $paidAmount
            : $debtSum;

        $debtor = User::find($userId);
        $creditor = User::whereNot('id', $userId)->first();

        $notes = __("resources.fields.notes_message.{$paymentStatus}", [
            'debtor' => $debtor?->name ?? 'Unknown',
            'creditor' => $creditor?->name ?? 'Unknown',
            'sum' => number_format($difference, 2, ',', ' '),
        ]);

        $set('notes', $notes);
    }

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('resources.sections.main'))
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->schema([
                        Group::make([
                            FormGrid::make([
                                'default' => 1,
                                'sm' => 2,
                            ])
                                ->schema([
                                    Select::make('user_id')
                                        ->label(__('resources.fields.debtor'))
                                        ->required(fn($record) => $record->user_id !== null)
                                        ->visible(fn($record) => $record->user_id !== null)
                                        ->allowHtml()
                                        ->options(fn() => User::all()->mapWithKeys(function ($user) {
                                            return [$user->getKey() => static::formatOptionWithIcon($user->name, $user->image)];
                                        })->toArray())
                                        ->searchable()
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpanFull(),

                                    DatePicker::make('date')
                                        ->label(__('resources.fields.date'))
                                        ->required()
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan([
                                            'default' => 1,
                                        ]),

                                    TextInput::make('debt_sum')
                                        ->label(__('resources.fields.debt_sum'))
                                        ->required()
                                        ->suffix('MDL')
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan([
                                            'default' => 1,
                                        ]),

                                    Textarea::make('notes')
                                        ->label(__('resources.fields.notes'))
                                        ->rows(3)
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpanFull(),
                                ]),
                        ]),

                        Group::make([
                            Forms\Components\View::make('filament.components.debt-calculation-details')
                        ]),

                        Group::make([
                            Select::make('payment_status')
                                ->label(__('resources.fields.payment_status'))
                                ->options(fn() => collect(['unpaid', 'partial', 'paid'])
                                    ->mapWithKeys(function ($key) {
                                        $name = __("resources.toggleButtons.options.$key");
                                        $color = __("resources.toggleButtons.color.$key");

                                        return [$key => static::formatOptionWithIcon($name, null, $color)];
                                    })
                                    ->toArray())
                                ->native(false)
                                ->allowHtml()
                                ->live()
                                ->afterStateUpdated(function ($state, $set, $get) {
                                    if (in_array($state, ['paid', 'partial'])) {
                                        if (empty($get('date_paid'))) {
                                            $set('date_paid', now()->format('Y-m-d'));
                                        }
                                    } else {
                                        $set('date_paid', null);
                                    }

                                    $set('partial_sum', $state === 'paid' ? $get('debt_sum') : 0);

                                    static::updateDebtNotes($get, $set);
                                })
                                ->required()
                                ->columnSpanFull(),

                            TextInput::make('partial_sum')
                                ->label(__('resources.fields.partial_sum'))
                                ->suffix('MDL')
                                ->numeric()
                                ->visible(fn($get) => $get('payment_status') === 'partial')
                                ->required(fn($get) => $get('payment_status') === 'partial')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, $set, $get) {
                                    if ($get('payment_status') === 'partial' && $state >= $get('debt_sum')) {
                                        $set('partial_sum', 0);
                                        $state = 0;

                                        Notification::make()
                                            ->title(__('resources.notifications.warn.debt.title'))
                                            ->body(__('resources.notifications.warn.debt.body'))
                                            ->warning()
                                            ->color('warning')
                                            ->duration(5000)
                                            ->send();
                                    }

                                    static::updateDebtNotes($get, $set, $state);
                                })
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                ]),

                            DatePicker::make('date_paid')
                                ->label(__('resources.fields.date_paid'))
                                ->visible(fn($get) => in_array($get('payment_status'), ['partial', 'paid']))
                                ->required(fn($get) => in_array($get('payment_status'), ['partial', 'paid']))
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                ]),
                        ])
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                            ]),
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

                        Stack::make([
                            TextColumn::make('')
                                ->state(
                                    fn($record) => $record->overpayment
                                        ? __('resources.fields.overpayment', ['user' => $record->overpayment->user->name])
                                        : __('resources.fields.no_overpayment')
                                )
                                ->color('primary')
                                ->alignment('right'),

                            TextColumn::make('overpayment.sum')
                                ->numeric()
                                ->money('MDL')
                                ->color('gray')
                                ->alignment('right'),
                        ])->columnSpan(2)

                    ])->extraAttributes([
                        'class' => 'min-h-[56px] justify-end px-3 py-1 rounded-t-xl bg-gray-100 dark:bg-white/5',
                    ]),
                Split::make([
                    ImageColumn::make('user.image')
                        ->circular()
                        ->height(100)
                        ->width(100)
                        ->grow(false),
                    Stack::make([
                        TableGrid::make([
                            'default' => 2
                        ])
                            ->schema([

                                TextColumn::make('user.name')
                                    ->size(TextColumnSize::Medium)
                                    ->weight(FontWeight::Bold)
                                    ->getStateUsing(fn($record) => $record->user?->name ?? 'Null')
                                    ->columnSpan(1),

                                TextColumn::make('date_paid')
                                    ->label(__('resources.fields.date_paid'))
                                    ->dateTime('d M. Y')
                                    ->color(fn($record) => match ($record?->payment_status) {
                                        'paid' => 'success',
                                        'partial' => 'warning',
                                        default => 'gray',
                                    })
                                    ->visible(fn($record) => in_array($record?->payment_status, ['paid', 'partial']))
                                    ->alignment('right')

                            ])->extraAttributes(fn($record) => $record->user_id ? [] : ['class' => 'invisible']),

                        TableGrid::make([
                            'default' => 3
                        ])
                            ->schema([
                                TextColumn::make('debt_sum')
                                    ->numeric()
                                    ->color('warning')
                                    ->money('MDL')
                                    ->columnSpan(1),

                                TextColumn::make('payment_status')
                                    ->badge()
                                    ->color(fn($state) => match ($state) {
                                        'paid' => 'success',
                                        'partial' => 'warning',
                                        'unpaid' => 'danger',
                                    })
                                    ->formatStateUsing(fn($state) => __('resources.toggleButtons.options.' . $state))
                                    ->alignment('right')
                                    ->columnSpan(2),
                            ])->grow()
                            ->extraAttributes(fn($record) => $record->user_id ? ['class' => 'py-2'] : ['class' => 'py-2 invisible']),

                        TextColumn::make('notes')
                            ->label(__('resources.fields.notes'))
                            ->color('gray')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: false),

                    ])
                ])->extraAttributes(fn($record) => $record->user_id
                    ? ['class' => 'py-2 ps-4 pe-4']
                    : ['class' => 'py-2 ps-4 pe-4', 'style' => 'gap: 0;']),

            ])->contentGrid([
                'md' => 2,
                'lg' => 1,
                'xl' => 2,
                '2xl' => 3,
            ])
            ->recordClasses('debt-record')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->label(__('resources.buttons.pay_off_debt'))
                    ->icon('heroicon-o-check')
                    ->visible(fn($record) => $record->payment_status !== 'paid')
                    ->extraAttributes(['style' => 'margin-left: auto;']),

            ])
            ->filters([
                
                SelectFilter::make('user')
                    ->label(__('resources.fields.user'))
                    ->relationship('user', 'name')
                    ->multiple()
                    ->preload()
                    ->placeholder(''),

                SelectFilter::make('payment_status')
                    ->label(__('resources.fields.payment_status'))
                    ->options([
                        'paid' => __('resources.toggleButtons.options.paid'),
                        'partial' => __('resources.toggleButtons.options.partial'),
                        'unpaid' => __('resources.toggleButtons.options.unpaid'),
                    ])
                    ->multiple()
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
                            ->when($data['sum_min'], fn(Builder $query, $min) => $query->where('debt_sum', '>=', $min))
                            ->when($data['sum_max'], fn(Builder $query, $max) => $query->where('debt_sum', '<=', $max));
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
            ->defaultGroup(
                TableGroup::make('date')
                    ->getTitleFromRecordUsing(fn(Debt $record): string => Carbon::parse($record->date)->format('Y'))
                    ->orderQueryUsing(fn(Builder $query) => $query->orderBy('date', 'desc'))
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
            'index' => Pages\ListDebts::route('/'),
            'edit' => Pages\EditDebt::route('/{record}/edit'),
        ];
    }
}
