<?php

namespace App\Filament\Resources\ExpenseChangeRequestResource\Widgets;

use App\Models\Expense;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SelectExpenseTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Выберите расход';

    public function table(Table $table): Table
    {
        return $table
            ->query(Expense::previousMonthsExpenses()->orderBy('date', 'desc'))
            ->searchPlaceHolder(__('resources.search_placeholder.resource.expense'))
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
            ])
            ->contentGrid([
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
            ->actions([
                Tables\Actions\Action::make('select')
                    ->label(__('resources.buttons.select'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (Expense $record) {
                        $this->dispatch('expense:selected', 
                            expenseId: $record->id,
                            userId: $record->user_id,
                            date: $record->date?->format('Y-m-d'),
                            categoryId: $record->category_id,
                            supplierId: $record->supplier_id,
                            sum: $record->sum,
                            notes: $record->notes,
                        );
                        $this->js('close()');
                    })
                    ->extraAttributes(['class' => 'ml-auto']),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->poll('30s');
    }
}
