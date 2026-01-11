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
            ->filters(\App\Filament\Resources\Base\BaseResource::getExpenseTableFilters())
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
