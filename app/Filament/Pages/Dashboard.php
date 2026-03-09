<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Carbon;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Schemas\Components\Group;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected string $view = 'filament.pages.dashboard';

    protected static string $routePath = '/familyBudget';

    protected static ?string $title = 'Главная';

    public function mountHasFilters(): void
    {
        $filtersSessionKey = $this->getFiltersSessionKey();

        if (session()->has($filtersSessionKey)) {
            $this->filters = session()->get($filtersSessionKey);
        }

        if (!$this->filters || empty(array_filter($this->filters, fn($v) => filled($v)))) {
            $this->filters = [
                'user_ids' => null,
                'category_ids' => null,
                'supplier_ids' => null,
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
                'sum_min' => null,
                'sum_max' => null,
            ];
        } else {
            $this->normalizeTableFilterValuesFromQueryString($this->filters);
        }
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getActiveDashboardFilterIndicators(): array
    {
        $filters = $this->filters ?? [];

        $indicators = [];

        $userIds = $this->normalizeIdFilter($filters['user_ids'] ?? null);
        if ($userIds !== []) {
            $users = User::query()
                ->whereIn('id', $userIds)
                ->pluck('name', 'id')
                ->all();

            $userNames = array_values(array_filter(array_map(
                static fn (int $id): ?string => $users[$id] ?? null,
                $userIds,
            )));

            if ($userNames !== []) {
                $indicators[] = [
                    'key' => 'user_ids',
                    'label' => __('resources.fields.user') . ': ' . implode(' & ', $userNames),
                ];
            }
        }

        $categoryIds = $this->normalizeIdFilter($filters['category_ids'] ?? null);
        if ($categoryIds !== []) {
            $categories = Category::query()
                ->whereIn('id', $categoryIds)
                ->pluck('name', 'id')
                ->all();

            $categoryNames = array_values(array_filter(array_map(
                static fn (int $id): ?string => $categories[$id] ?? null,
                $categoryIds,
            )));

            if ($categoryNames !== []) {
                $indicators[] = [
                    'key' => 'category_ids',
                    'label' => __('resources.fields.category') . ': ' . implode(' & ', $categoryNames),
                ];
            }
        }

        $supplierIds = $this->normalizeIdFilter($filters['supplier_ids'] ?? null);
        if ($supplierIds !== []) {
            $suppliers = Supplier::query()
                ->whereIn('id', $supplierIds)
                ->pluck('name', 'id')
                ->all();

            $supplierNames = array_values(array_filter(array_map(
                static fn (int $id): ?string => $suppliers[$id] ?? null,
                $supplierIds,
            )));

            if ($supplierNames !== []) {
                $indicators[] = [
                    'key' => 'supplier_ids',
                    'label' => __('resources.fields.supplier') . ': ' . implode(' & ', $supplierNames),
                ];
            }
        }

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if (filled($dateFrom) || filled($dateTo)) {
            $formattedFrom = filled($dateFrom)
                ? Carbon::parse($dateFrom)->translatedFormat('d F Y')
                : null;
            $formattedTo = filled($dateTo)
                ? Carbon::parse($dateTo)->translatedFormat('d F Y')
                : null;

            if (filled($dateFrom) && ! filled($dateTo)) {
                $label = __('resources.filters.date_from') . $formattedFrom;
            } elseif (! filled($dateFrom) && filled($dateTo)) {
                $label = __('resources.filters.date_until') . $formattedTo;
            } else {
                $label = __('resources.filters.date_range') . $formattedFrom . ' - ' . $formattedTo;
            }

            $indicators[] = [
                'key' => 'date_range',
                'label' => $label,
            ];
        }

        $sumMin = $filters['sum_min'] ?? null;
        $sumMax = $filters['sum_max'] ?? null;

        if (filled($sumMin) || filled($sumMax)) {
            $formattedMin = filled($sumMin)
                ? number_format((float) $sumMin, 2, ',', ' ') . ' MDL'
                : null;
            $formattedMax = filled($sumMax)
                ? number_format((float) $sumMax, 2, ',', ' ') . ' MDL'
                : null;

            if (filled($sumMin) && ! filled($sumMax)) {
                $label = __('resources.filters.sum_min') . $formattedMin;
            } elseif (! filled($sumMin) && filled($sumMax)) {
                $label = __('resources.filters.sum_max') . $formattedMax;
            } else {
                $label = __('resources.filters.sum_range') . $formattedMin . ' - ' . $formattedMax;
            }

            $indicators[] = [
                'key' => 'sum_range',
                'label' => $label,
            ];
        }

        return $indicators;
    }

    public function removeDashboardFilter(string $key): void
    {
        if (! is_array($this->filters ?? null)) {
            $this->filters = [];
        }

        switch ($key) {
            case 'date_range':
                $this->filters['date_from'] = null;
                $this->filters['date_to'] = null;
                break;

            case 'sum_range':
                $this->filters['sum_min'] = null;
                $this->filters['sum_max'] = null;
                break;

            default:
                $this->filters[$key] = null;
                break;
        }

        session()->put($this->getFiltersSessionKey(), $this->filters);
    }

    public function removeDashboardAllFilters(): void
    {
        $this->filters = [
            'user_ids' => null,
            'category_ids' => null,
            'supplier_ids' => null,
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
            'sum_min' => null,
            'sum_max' => null,
        ];

        session()->put($this->getFiltersSessionKey(), $this->filters);
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIdFilter(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $id): ?int => is_numeric($id) ? (int) $id : null,
            $value,
        )));
    }

    public function resetDashboardFilters(): void
    {
        $this->removeDashboardAllFilters();
        $this->dispatch('close-modal', id: 'fi-' . $this->getId() . '-action-0');
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
                ->action(function (array $data): void {
                    $this->filters = $data;
                    session()->put($this->getFiltersSessionKey(), $this->filters);
                })
                ->modalHeading(new HtmlString('
                    <div class="flex items-center justify-between w-full">
                        <span>Фильтры</span>
                        <button type="button"
                            wire:click="resetDashboardFilters"
                            class="fi-link fi-link-size-sm text-sm font-semibold text-danger-600 hover:text-danger-500 dark:text-danger-400 dark:hover:text-danger-300 transition">
                            Сбросить
                        </button>
                    </div>
                '))
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
                        ->columnSpanFull(),

                    Group::make([
                        DatePicker::make('date_from')
                            ->label(__('resources.filters.date_from'))
                            ->closeOnDateSelection(),

                        DatePicker::make('date_to')
                            ->label(__('resources.filters.date_until'))
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
