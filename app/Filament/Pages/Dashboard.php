<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/familyBudget';

    protected static ?string $title = 'Главная';

    public function getColumns(): int | array
    {
        return 2;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Фильтры аналитики')
                    ->description('Один набор фильтров применяется ко всем виджетам расходов.')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'lg' => 2,
                        '2xl' => 4,
                    ])
                    ->schema([
                        Select::make('user_ids')
                            ->label(__('resources.fields.user'))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->options(fn(): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->columnSpanFull(),

                        Select::make('category_ids')
                            ->label(__('resources.fields.category'))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->options(fn(): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->columnSpanFull(),

                        Select::make('supplier_ids')
                            ->label(__('resources.fields.supplier'))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->options(fn(): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->columnSpanFull(),

                        Group::make([
                            DatePicker::make('date_from')
                                ->label(__('resources.filters.date_from'))
                                ->native(false)
                                ->default(now()->startOfMonth())
                                ->closeOnDateSelection(),

                            DatePicker::make('date_to')
                                ->label(__('resources.filters.date_until'))
                                ->native(false)
                                ->default(now()->endOfMonth())
                                ->closeOnDateSelection(),
                        ])->columns(2)
                            ->columnSpanFull(),

                        Group::make([
                            TextInput::make('sum_min')
                                ->label(__('resources.filters.sum_min'))
                                ->numeric()
                                ->prefix('MDL')
                                ->columnSpan(1),

                            TextInput::make('sum_max')
                                ->label(__('resources.filters.sum_max'))
                                ->numeric()
                                ->prefix('MDL')
                                ->columnSpan(1),
                        ])->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
