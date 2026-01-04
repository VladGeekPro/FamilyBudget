<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OverpaymentResource\Pages;
use App\Models\Overpayment;
use App\Filament\Resources\Base\BaseResource;
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

class OverpaymentResource extends BaseResource
{
    protected static ?string $model = Overpayment::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'переплату';

    protected static ?string $pluralModelLabel = 'Переплаты';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $defaultSortColumn = null;

    protected static function getTableActions(): array
    {
        return [
            Tables\Actions\ViewAction::make()
                ->extraAttributes(['class' => 'ml-auto']),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make(__('resources.sections.main'))
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->schema([

                        FormGrid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([

                                Group::make([
                                    Select::make('user_id')
                                        ->label(__('resources.fields.user'))
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn($livewire) => $livewire->validateOnly('data.user_id'))
                                        ->allowHtml()
                                        ->options(fn() => User::all()->mapWithKeys(function ($user) {
                                            return [$user->getKey() => static::formatOptionWithIcon($user->name, $user->image)];
                                        })->toArray())
                                        ->getSearchResultsUsing(function (string $search) {
                                            $users = User::where('name', 'like', "%{$search}%")->limit(50)->get();
                                            return $users->mapWithKeys(function ($user) {
                                                return [$user->getKey() => static::formatOptionWithIcon($user->name, $user->image)];
                                            })->toArray();
                                        })
                                        ->getOptionLabelUsing(function ($value): string {
                                            $user = User::find($value);
                                            return static::formatOptionWithIcon($user->name, $user->image);
                                        })
                                        ->optionsLimit(10)
                                        ->searchable()
                                        ->preload()
                                        ->selectablePlaceholder(false)
                                        ->loadingMessage(__('resources.notifications.load.users'))
                                        ->noSearchResultsMessage(__('resources.notifications.skip.users'))
                                        ->default(auth()->user()->id)
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
                                    ->required()
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
                        TextColumn::make('created_at')
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
                    Stack::make([
                        TableGrid::make([
                            'default' => 2
                        ])
                            ->schema([
                                TextColumn::make('user.name')
                                    ->label(__('resources.fields.user'))
                                    ->size(TextColumnSize::Medium)
                                    ->weight(FontWeight::Bold)
                                    ->searchable()
                                    ->columnSpan(1),
                                TextColumn::make('sum')
                                    ->numeric(decimalPlaces: 2)
                                    ->color('success')
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
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
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
            ->defaultGroup(
                TableGroup::make('created_at')
                    ->getTitleFromRecordUsing(function (Overpayment $record): string {

                        $filters = session('tableFilters', []);

                        $filteredQuery = Overpayment::query();

                        if (!empty($filters['user'])) {
                            $filteredQuery->whereIn('overpayments.user_id', $filters['user']);
                        }

                        if (!empty($filters['date']['date_from'])) {
                            $filteredQuery->whereDate('overpayments.created_at', '>=', $filters['date']['date_from']);
                        }

                        if (!empty($filters['date']['date_until'])) {
                            $filteredQuery->whereDate('overpayments.created_at', '<=', $filters['date']['date_until']);
                        }

                        if (!empty($filters['sum']['sum_min'])) {
                            $filteredQuery->where('overpayments.sum', '>=', $filters['sum']['sum_min']);
                        }

                        if (!empty($filters['sum']['sum_max'])) {
                            $filteredQuery->where('overpayments.sum', '<=', $filters['sum']['sum_max']);
                        }

                        if (!empty($filters['search'])) {
                            $search = $filters['search'];
                            $filteredQuery->where(function ($query) use ($search) {
                                $query->where('overpayments.notes', 'like', "%{$search}%");
                            });
                        }

                        $month = $record->created_at->format('Y');
                        $year  = $record->created_at->format('m');

                        $filteredQuery->whereYear('overpayments.created_at', $month)->whereMonth('overpayments.created_at', $year);

                        $sum = $filteredQuery->sum('overpayments.sum');

                        $monthName = mb_convert_case(
                            $record->created_at->locale('ru')->translatedFormat('F Y'),
                            MB_CASE_TITLE,
                            'UTF-8'
                        );

                        return "{$monthName} — " . number_format($sum, 2, ',', ' ') . " MDL";
                    })
                    ->orderQueryUsing(
                        fn(Builder $query) => $query->orderBy('created_at', 'desc')
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
            'index' => Pages\ListOverpayments::route('/'),
            'create' => Pages\CreateOverpayment::route('/create'),
            'edit' => Pages\EditOverpayment::route('/{record}/edit'),
            'view' => Pages\ViewOverpayment::route('/{record}'),
        ];
    }
}
