<?php

namespace App\Filament\Resources\Base;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Overpayment;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

abstract class BaseResource extends Resource
{
    /** Можно переопределять в дочерних ресурсах */
    protected static ?string $defaultSortColumn = 'name';
    protected static string  $defaultSortDirection = 'asc';

    /** Пагинация по умолчанию (можно переопределить в дочерних) */
    protected static int    $defaultPerPage = 25;
    protected static array  $defaultPerPageOptions = [10, 25, 50, 100, 'all'];

    protected static function getModelBase(): string
    {
        return Str::snake(class_basename(static::getModel()));
    }

    protected static function getTableActions(): array
    {
        $modelBase = static::getModelBase();

        return [
            \Filament\Actions\EditAction::make()
                ->extraAttributes(['style' => 'margin-left: auto;']),

            \Filament\Actions\DeleteAction::make()
                ->successNotificationTitle(__("resources.notifications.delete.{$modelBase}")),
        ];
    }

    public static function table(Table $table): Table
    {
        // пагинация
        $table
            ->defaultPaginationPageOption(static::$defaultPerPage)
            ->paginated(static::$defaultPerPageOptions);

        // дефолтная сортировка (если указана колонка)
        if (filled(static::$defaultSortColumn)) {
            $table->defaultSort(static::$defaultSortColumn, static::$defaultSortDirection);
        }

        // плейсхолдер поиска из переводов:
        $modelBase = static::getModelBase();
        $key = "resources.search_placeholder.resource.{$modelBase}";
        $table->searchPlaceholder(
            Lang::has($key)
                ? __($key)
                : __('resources.search_placeholder.missing')
        );

        //действия
        $table->actions(
            static::getTableActions()
        );

        return $table;
    }

    public static function formatOptionWithIcon(string $name, ?string $image, ?string $bgColor = null): string
    {
        return view('filament.components.select-user-result')
            ->with('name', $name)
            ->with('image', $image)
            ->with('bgColor', $bgColor)
            ->render();
    }

    protected function getDefaultDeleteAction(?string $label = null): DeleteAction
    {
        return DeleteAction::make()
            ->label($label ?? __('Удалить'))
            ->successNotificationTitle(__('Удалено!'));
    }

    public static function canEdit(Model $record): bool
    {

        if ($record instanceof Expense) {
            return $record->date->gte(now()->startOfMonth());
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {

        if ($record instanceof Expense) {
            return $record->date->gte(now()->startOfMonth());
        }

        return true;
    }

    protected static function makeRelationshipFilter(
        string $name,
        string $label,
        string $relationship,
        string $titleColumn = 'name',
        bool $searchable = false
    ): \Filament\Tables\Filters\SelectFilter {
        $filter = \Filament\Tables\Filters\SelectFilter::make($name)
            ->label($label)
            ->relationship($relationship, $titleColumn)
            ->multiple()
            ->preload()
            ->optionsLimit(10)
            ->placeholder('');

        if ($searchable) {
            $filter->searchable();
        }

        return $filter;
    }

    protected static function makeSelectOptionsFilter(
        string $name,
        string $label,
        array | \Closure $options,
        bool $searchable = false
    ): \Filament\Tables\Filters\SelectFilter {
        $filter = \Filament\Tables\Filters\SelectFilter::make($name)
            ->label($label)
            ->options($options)
            ->multiple()
            ->optionsLimit(10)
            ->placeholder('');

        if ($searchable) {
            $filter->searchable();
        }

        return $filter;
    }

    protected static function makeDateRangeFilter(
        string $filterName = 'date',
        string $label = 'resources.fields.date',
        string $column = 'date'
    ): \Filament\Tables\Filters\Filter {
        return \Filament\Tables\Filters\Filter::make($filterName)
            ->label(__($label))
            ->schema([
                \Filament\Forms\Components\DatePicker::make("date_from")->label(__('resources.filters.date_from')),
                \Filament\Forms\Components\DatePicker::make("date_until")->label(__('resources.filters.date_until')),
            ])
            ->columns(2)
            ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) use ($column): \Illuminate\Database\Eloquent\Builder {
                return $query
                    ->when(
                        $data['date_from'] ?? null,
                        fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate($column, '>=', $date),
                    )
                    ->when(
                        $data['date_until'] ?? null,
                        fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate($column, '<=', $date),
                    );
            })
            ->indicateUsing(function (array $data): ?string {
                if (! ($data['date_from'] ?? null) && ! ($data['date_until'] ?? null)) {
                    return null;
                } elseif (($data['date_from'] ?? null) && ! ($data['date_until'] ?? null)) {
                    return __('resources.filters.date_from') . \Carbon\Carbon::parse($data['date_from'])->translatedFormat('d F Y');
                } elseif (! ($data['date_from'] ?? null) && ($data['date_until'] ?? null)) {
                    return __('resources.filters.date_until') . \Carbon\Carbon::parse($data['date_until'])->translatedFormat('d F Y');
                } else {
                    return __('resources.filters.date_range') . \Carbon\Carbon::parse($data['date_from'])->translatedFormat('d F Y') . ' - ' . \Carbon\Carbon::parse($data['date_until'])->translatedFormat('d F Y');
                }
            });
    }

    protected static function makeAmountRangeFilter(
        string $column = 'sum',
        string $label = 'resources.fields.sum'
    ): \Filament\Tables\Filters\Filter {
        return \Filament\Tables\Filters\Filter::make('sum')
            ->label(__($label))
            ->schema([
                \Filament\Forms\Components\TextInput::make('sum_min')
                    ->numeric()
                    ->prefix('MDL')
                    ->label(__('resources.filters.sum_min')),
                \Filament\Forms\Components\TextInput::make('sum_max')
                    ->numeric()
                    ->prefix('MDL')
                    ->label(__('resources.filters.sum_max')),
            ])
            ->columns(2)
            ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) use ($column): \Illuminate\Database\Eloquent\Builder {
                return $query
                    ->when($data['sum_min'] ?? null, fn(\Illuminate\Database\Eloquent\Builder $query, $min) => $query->where($column, '>=', $min))
                    ->when($data['sum_max'] ?? null, fn(\Illuminate\Database\Eloquent\Builder $query, $max) => $query->where($column, '<=', $max));
            })
            ->indicateUsing(function (array $data): ?string {
                $min = $data['sum_min'] ?? null;
                $max = $data['sum_max'] ?? null;

                if (! $min && ! $max) {
                    return null;
                } elseif ($min && ! $max) {
                    return __('resources.filters.sum_min') . number_format($min, 2, ',', ' ') . ' MDL';
                } elseif (! $min && $max) {
                    return __('resources.filters.sum_max') . number_format($max, 2, ',', ' ') . ' MDL';
                } else {
                    return __('resources.filters.sum_range') . number_format($min, 2, ',', ' ') . ' - ' . number_format($max, 2, ',', ' ') . ' MDL';
                }
            });
    }

    protected static function getCommonDateAndAmountFilters(
        string $dateColumn = 'date',
        string $sumColumn = 'sum',
        string $dateFilterName = 'date'
    ): array {
        return [
            static::makeDateRangeFilter($dateFilterName, 'resources.fields.date', $dateColumn),
            static::makeAmountRangeFilter($sumColumn, 'resources.fields.sum'),
        ];
    }

    protected static function getCommonUserDateAndAmountFilters(
        string $userRelationship = 'user',
        string $dateColumn = 'date',
        string $sumColumn = 'sum',
        string $dateFilterName = 'date'
    ): array {
        return array_merge(
            [
                static::makeRelationshipFilter('user', __('resources.fields.user'), $userRelationship),
            ],
            static::getCommonDateAndAmountFilters($dateColumn, $sumColumn, $dateFilterName),
        );
    }

    public static function getExpenseTableFilters(bool $onlyPreviousMonthsForId = false): array
    {
        return array_merge(
            [
                static::makeSelectOptionsFilter(
                    'id',
                    __('resources.fields.id'),
                    function () use ($onlyPreviousMonthsForId) {
                        $query = Expense::query()
                            ->with('supplier:id,name')
                            ->orderBy('date', 'desc');

                        if ($onlyPreviousMonthsForId) {
                            $query->whereDate('date', '<', now()->startOfMonth());
                        }

                        return $query
                            ->get()
                            ->mapWithKeys(fn($expense) => [
                                $expense->id => "#{$expense->id} - {$expense->supplier->name} - {$expense->sum} MDL ({$expense->date->format('d.m.Y')})",
                            ]);
                    },
                    true
                )->getSearchResultsUsing(function (string $search) {
                    return Expense::previousMonthsExpenses()
                        ->orderBy('id', 'desc')
                        ->where(function ($query) use ($search) {
                            $query->whereHas('supplier', function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%");
                            })
                                ->orWhere('notes', 'like', "%{$search}%")
                                ->orWhere('sum', 'like', "%{$search}%")
                                ->orWhere('id', $search);
                        })
                        ->limit(10)
                        ->get()
                        ->mapWithKeys(function ($expense) {
                            return [$expense->id => "#{$expense->id} - {$expense->supplier->name} - {$expense->sum} MDL ({$expense->date->format('d.m.Y')})"];
                        });
                }),
                static::makeRelationshipFilter('user', __('resources.fields.user'), 'user', 'name', true),
                static::makeRelationshipFilter('category', __('resources.fields.category'), 'category', 'name', true),
                static::makeRelationshipFilter('supplier', __('resources.fields.supplier'), 'supplier', 'name', true),
            ],
            static::getCommonDateAndAmountFilters(),
        );
    }
}
