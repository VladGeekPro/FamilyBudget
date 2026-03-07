<?php

namespace App\Filament\Tables\Concerns;

use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;

trait HasExpenseCardTableLayout
{
    protected static function getExpenseCardColumns(): array
    {
        return [
            TableGrid::make([
                'default' => 2,
            ])->schema([
                TextColumn::make('date')
                    ->label(__('resources.fields.date'))
                    ->dateTime('d M. Y')
                    ->color('info')
                    ->columnSpan(1),

                ImageColumn::make('user.image')
                    ->circular()
                    ->imageHeight(40)
                    ->imageWidth(40)
                    ->extraAttributes(['class' => 'justify-end']),
            ]),

            Split::make([
                TableGrid::make()
                    ->columns(1)
                    ->schema([
                        ImageColumn::make('supplier.image')
                            ->circular()
                            ->imageHeight(80)
                            ->imageWidth(80),
                    ])->grow(false),

                Stack::make([
                    TableGrid::make([
                        'default' => 3,
                    ])->schema([
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
                            ->columnSpan(1)
                            ->extraAttributes(['class' => 'justify-end']),
                    ])->grow(),

                    TextColumn::make('notes')
                        ->label(__('resources.fields.notes'))
                        ->html()
                        ->formatStateUsing(fn ($state) => Str::markdown((string) $state))
                        ->searchable()
                        ->color('gray')
                        ->limit(100),
                ]),
            ])->extraAttributes(['class' => 'py-2']),
        ];
    }

    protected static function getExpenseCardContentGrid(): array
    {
        return [
            'md' => 2,
            'lg' => 1,
            'xl' => 2,
            '2xl' => 3,
        ];
    }
}
