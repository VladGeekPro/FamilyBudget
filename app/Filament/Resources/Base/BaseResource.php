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
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Notifications\Notification;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

abstract class BaseResource extends Resource
{
    /** Можно переопределять в дочерних ресурсах */
    protected static ?string $defaultSortColumn = 'name';
    protected static string  $defaultSortDirection = 'asc';

    /** Пагинация по умолчанию (можно переопределить в дочерних) */
    protected static int    $defaultPerPage   = 10;
    protected static array  $defaultPerPageOptions   = [10, 25, 50, 100, 'all'];

    protected static function getModelBase(): string
    {
        return Str::snake(class_basename(static::getModel()));
    }

    protected static function getTableActions(): array
    {
        $modelBase = static::getModelBase();

        return [
            Tables\Actions\EditAction::make()
                ->extraAttributes(['style' => 'margin-left: auto;']),

            Tables\Actions\DeleteAction::make()
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

    protected static function applyFieldConditions($field, bool $forExpense, bool $isCurrentField)
    {
        if (!$forExpense) {
            if ($isCurrentField) {
                $field->disabled(true)->dehydrated(true);
            } else {
                $field
                    ->required(fn($get) => $get('action_type') !== 'delete' && $field->getName() !== 'requested_notes')
                    ->disabled(fn($get) => $get('action_type') === 'delete');
            }

            $selectFields = ['user_id', 'category_id', 'supplier_id'];
            $fieldName = $field->getName();

            $baseFieldName = str_replace(['current_', 'requested_'], '', $fieldName);
            if (in_array($baseFieldName, $selectFields)) {
                $field
                    ->placeholder('Выбрать вариант')
                    ->extraAttributes(fn($get) => $get('action_type') === 'edit' ? ['class' => 'min-h-[52px] items-center'] : []);
            }
        } else {
            $field->required();
        }

        return $field;
    }

    public static function getExpenseFormFields(string $prefix = '', bool $forExpense = true): array
    {
        $isCurrentField = str_starts_with($prefix, 'current_');

        $dateField = DatePicker::make($prefix . 'date')
            ->label(__('resources.fields.date'))
            ->live(onBlur: true)
            ->afterStateUpdated(fn($livewire) => $livewire->validateOnly('data.' . $prefix . 'date'));

        static::applyFieldConditions($dateField, $forExpense, $isCurrentField);

        $userField =  Select::make($prefix . 'user_id')
            ->label(__('resources.fields.payer'))
            ->live(onBlur: true)
            ->afterStateUpdated(fn($livewire) => $livewire->validateOnly('data.' . $prefix . 'user_id'))
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
            ->noSearchResultsMessage(__('resources.notifications.skip.users'));

        static::applyFieldConditions($userField, $forExpense, $isCurrentField);

        if ($forExpense) {
            $dateField
                ->minDate(now()->startOfMonth())
                ->default(now());

            $userField
                ->default(auth()->user()->id);
        }

        $categoryField = Select::make($prefix . 'category_id')
            ->label(__('resources.fields.category'))
            ->live(onBlur: true)
            ->afterStateUpdated(function ($livewire, $set, $state, $get) use ($prefix) {
                $supplierId = $get($prefix . 'supplier_id');

                if (filled($supplierId)) {
                    $supplier = Supplier::find($supplierId);

                    if (blank($state) || ($supplier && $supplier->category_id !== $state)) {
                        $set($prefix . 'supplier_id', null);

                        Notification::make()
                            ->title(__('resources.notifications.warn.expense.title'))
                            ->body(__('resources.notifications.warn.expense.body'))
                            ->warning()
                            ->color('warning')
                            ->duration(5000)
                            ->send();
                    }

                    $livewire->validate([
                        'data.' . $prefix . 'supplier_id' => 'required',
                        'data.' . $prefix . 'category_id' => 'required'
                    ]);
                }

                $livewire->validateOnly('data.' . $prefix . 'category_id');
            })
            ->allowHtml()
            ->options(fn() => Category::all()->mapWithKeys(function ($category) {
                return [$category->getKey() => static::formatOptionWithIcon($category->name, $category->image)];
            })->toArray())
            ->getSearchResultsUsing(function (string $search) {
                $categories = Category::where('name', 'like', "%{$search}%")->limit(50)->get();
                return $categories->mapWithKeys(function ($category) {
                    return [$category->getKey() => static::formatOptionWithIcon($category->name, $category->image)];
                })->toArray();
            })
            ->getOptionLabelUsing(function ($value): string {
                $category = Category::find($value);
                return static::formatOptionWithIcon($category->name, $category->image);
            })
            ->optionsLimit(10)
            ->searchable()
            ->preload()
            ->loadingMessage(__('resources.notifications.load.categories'))
            ->noSearchResultsMessage(__('resources.notifications.skip.categories'));

        static::applyFieldConditions($categoryField, $forExpense, $isCurrentField);

        $supplierField = Select::make($prefix . 'supplier_id')
            ->label(__('resources.fields.supplier'))
            ->live(onBlur: true)
            ->afterStateUpdated(function ($livewire, $set, $state, $get) use ($prefix) {
                $livewire->validateOnly('data.' . $prefix . 'supplier_id');
                if (filled($state) && empty($get($prefix . 'category_id'))) {
                    $set($prefix . 'category_id', Supplier::find($state)->category_id);
                    $livewire->validateOnly('data.' . $prefix . 'category_id');
                }
            })
            ->allowHtml()
            ->options(function ($get) use ($prefix) {
                $query = Supplier::query();

                if (filled($get($prefix . 'category_id'))) {
                    $query->where('category_id', $get($prefix . 'category_id'));
                }

                return $query->get()->mapWithKeys(function ($supplier) {
                    return [$supplier->getKey() => static::formatOptionWithIcon($supplier->name, $supplier->image)];
                })->toArray();
            })
            ->getSearchResultsUsing(function (string $search, $get) use ($prefix) {
                $query = Supplier::query()->where('name', 'like', "%{$search}%");

                if (filled($get($prefix . 'category_id'))) {
                    $query->where('category_id', $get($prefix . 'category_id'));
                }

                return $query->limit(50)->get()->mapWithKeys(function ($supplier) {
                    return [$supplier->getKey() => static::formatOptionWithIcon($supplier->name, $supplier->image)];
                })->toArray();
            })
            ->getOptionLabelUsing(function ($value): string {
                $supplier = Supplier::find($value);
                return static::formatOptionWithIcon($supplier->name, $supplier->image);
            })
            ->optionsLimit(10)
            ->searchable()
            ->preload()
            ->selectablePlaceholder(false)
            ->loadingMessage(__('resources.notifications.load.suppliers'))
            ->noSearchResultsMessage(__('resources.notifications.skip.suppliers'));

        static::applyFieldConditions($supplierField, $forExpense, $isCurrentField);

        $sumField = TextInput::make($prefix . 'sum')
            ->label(__('resources.fields.sum'))
            ->suffix('MDL')
            ->numeric()
            ->placeholder('0.00');

        static::applyFieldConditions($sumField, $forExpense, $isCurrentField);

        $notesField = MarkdownEditor::make($prefix . 'notes')
            ->label(__('resources.fields.notes'))
            ->disableToolbarButtons([
                'attachFiles',
                'blockquote',
                'codeBlock',
                'heading',
                'table',
                'link',
            ])
            ->columnSpanFull();

        static::applyFieldConditions($notesField, $forExpense, $isCurrentField);

        return [
            TextInput::make('id')
                ->label(__('resources.fields.id'))
                ->disabled()
                ->visible(fn(?Model $record) => $record !== null && $record instanceof Expense)
                ->columnSpanFull(),

            $dateField,

            $userField,

            Group::make()
                ->columns([
                    'default' => 1,
                    'xl' => 2,
                ])
                ->schema([
                    $categoryField,
                    $supplierField,
                ]),

            $sumField,

            $notesField,
        ];
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

    public static function getExpenseTableFilters(): array
    {
        return [

            \Filament\Tables\Filters\SelectFilter::make('id')
                ->label(__('resources.fields.id'))
                ->multiple()
                ->preload()
                ->options(function () {
                    return Expense::orderBy('date', 'desc')
                        ->get()
                        ->mapWithKeys(fn($expense) => [
                            $expense->id => "#{$expense->id} - {$expense->supplier->name} - {$expense->sum} MDL ({$expense->date->format('d.m.Y')})"
                        ]);
                })
                ->placeholder(''),
            \Filament\Tables\Filters\SelectFilter::make('user')
                ->label(__('resources.fields.user'))
                ->relationship('user', 'name')
                ->multiple()
                ->preload()
                ->placeholder(''),
            \Filament\Tables\Filters\SelectFilter::make('category')
                ->label(__('resources.fields.category'))
                ->relationship('category', 'name')
                ->multiple()
                ->preload()
                ->placeholder(''),
            \Filament\Tables\Filters\SelectFilter::make('supplier')
                ->label(__('resources.fields.supplier'))
                ->relationship('supplier', 'name')
                ->multiple()
                ->preload()
                ->placeholder(''),
            \Filament\Tables\Filters\Filter::make('date')
                ->label(__('resources.fields.date'))
                ->form([
                    \Filament\Forms\Components\DatePicker::make("date_from")->label(__('resources.filters.date_from')),
                    \Filament\Forms\Components\DatePicker::make("date_until")->label(__('resources.filters.date_until')),
                ])->columns(2)
                ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                    return $query
                        ->when(
                            $data['date_from'],
                            fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('date', '>=', $date),
                        )
                        ->when(
                            $data['date_until'],
                            fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('date', '<=', $date),
                        );
                })
                ->indicateUsing(function (array $data): ?string {
                    if (! $data['date_from'] && ! $data['date_until']) {
                        return null;
                    } elseif ($data['date_from'] && ! $data['date_until']) {
                        return 'С ' . \Carbon\Carbon::parse($data['date_from'])->translatedFormat('d F Y');
                    } elseif (! $data['date_from'] && $data['date_until']) {
                        return 'До ' . \Carbon\Carbon::parse($data['date_until'])->translatedFormat('d F Y');
                    } else {
                        return 'Период: ' . \Carbon\Carbon::parse($data['date_from'])->translatedFormat('d F Y') . ' – ' . \Carbon\Carbon::parse($data['date_until'])->translatedFormat('d F Y');
                    }
                }),
            \Filament\Tables\Filters\Filter::make('sum')
                ->label(__('resources.fields.sum'))
                ->form([
                    \Filament\Forms\Components\TextInput::make('sum_min')
                        ->numeric()
                        ->label(__('resources.filters.sum_min')),
                    \Filament\Forms\Components\TextInput::make('sum_max')
                        ->numeric()
                        ->label(__('resources.filters.sum_max')),
                ])
                ->columns(2)
                ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                    return $query
                        ->when($data['sum_min'], fn(\Illuminate\Database\Eloquent\Builder $query, $min) => $query->where('sum', '>=', $min))
                        ->when($data['sum_max'], fn(\Illuminate\Database\Eloquent\Builder $query, $max) => $query->where('sum', '<=', $max));
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
        ];
    }
}
