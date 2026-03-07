<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Schemas\Components\Group;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected static string $routePath = '/familyBudget';

    protected static ?string $title = 'Главная';

    public function mount(): void
    {
        if (!$this->filters || empty(array_filter($this->filters))) {
            $this->filters = [
                'user_ids' => null,
                'category_ids' => null,
                'supplier_ids' => null,
                'date_from' => now()->startOfMonth(),
                'date_to' => now()->endOfMonth(),
                'sum_min' => null,
                'sum_max' => null,
            ];
        }
    }

    public function getColumns(): int | array
    {
        return 2;
    }

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->badge(function () {
                    $filters = $this->filters ?? [];
                    $count = count(array_filter($filters, fn($value) => filled($value)));
                    return $count ?: null;
                })
                ->badgeColor('primary')
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
                        ->live()
                        ->options(fn(): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->afterStateUpdated(function ($state, $set, $get) {
                            $selectedCategories = $state ?? [];
                            $selectedSuppliers = $get('supplier_ids') ?? [];

                            if (filled($selectedCategories) && filled($selectedSuppliers)) {
                                $validSuppliers = Supplier::whereIn('id', $selectedSuppliers)
                                    ->whereIn('category_id', $selectedCategories)
                                    ->pluck('id')
                                    ->toArray();

                                if (count($validSuppliers) !== count($selectedSuppliers)) {
                                    $set('supplier_ids', $validSuppliers);

                                    \Filament\Notifications\Notification::make()
                                        ->title(__('resources.notifications.warn.expense.title'))
                                        ->body(__('resources.notifications.warn.expense.body'))
                                        ->warning()
                                        ->send();
                                }
                            }
                        })
                        ->optionsLimit(10)
                        ->noOptionsMessage('Нет вариантов, соответствующих вашему запросу.')
                        ->columnSpanFull(),

                    Select::make('supplier_ids')
                        ->label(__('resources.fields.supplier'))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->options(function ($get) {
                            $categoryIds = $get('category_ids');

                            $query = Supplier::query()->orderBy('name');

                            if (!empty($categoryIds)) {
                                $query->whereIn('category_id', $categoryIds);
                            }

                            return $query->pluck('name', 'id')->all();
                        })
                        ->optionsLimit(10)
                        ->noOptionsMessage('Нет вариантов, соответствующих вашему запросу.')
                        ->columnSpanFull(),

                    Group::make([
                        DatePicker::make('date_from')
                            ->label(__('resources.filters.date_from'))
                            ->native(false)
                            ->closeOnDateSelection(),

                        DatePicker::make('date_to')
                            ->label(__('resources.filters.date_until'))
                            ->native(false)
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
        ];
    }
}
