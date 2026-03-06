<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Arr;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected static string $routePath = '/familyBudget';

    protected static ?string $title = 'Главная';

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
                    return $count ?? null;
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
        ];
    }
}
