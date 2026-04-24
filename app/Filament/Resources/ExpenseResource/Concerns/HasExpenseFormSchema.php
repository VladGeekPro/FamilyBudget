<?php

namespace App\Filament\Resources\ExpenseResource\Concerns;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait HasExpenseFormSchema
{
    protected static function applyExpenseFieldConditions($field, bool $forExpense, bool $isCurrentField)
    {
        $fieldName = $field->getName();

        if (! $forExpense) {
            if ($isCurrentField) {
                $field
                    ->disabled(true)
                    ->dehydrated(fn ($get) => $get('action_type') !== 'create');
            } else {
                $field
                    ->required(fn ($get) => $get('action_type') !== 'delete' && $fieldName !== 'requested_notes')
                    ->disabled(fn ($get) => $get('action_type') === 'delete')
                    ->dehydrated(fn ($get) => $get('action_type') !== 'delete');
            }

            $selectFields = ['user_id', 'category_id', 'supplier_id'];
            $baseFieldName = str_replace(['current_', 'requested_'], '', $fieldName);

            if (in_array($baseFieldName, $selectFields)) {
                $field
                    ->placeholder('Выбрать вариант')
                    ->extraAttributes(fn ($get) => $get('action_type') === 'edit' ? ['class' => 'min-h-[52px] items-center'] : []);
            } elseif ($baseFieldName === 'date') {
                $field
                    ->maxDate(now()->subMonth()->endOfMonth());
            }
        } elseif ($fieldName !== 'notes') {
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
            ->afterStateUpdated(fn ($livewire) => $livewire->validateOnly('data.' . $prefix . 'date'));

        static::applyExpenseFieldConditions($dateField, $forExpense, $isCurrentField);

        $userField = Select::make($prefix . 'user_id')
            ->label(__('resources.fields.payer'))
            ->live(onBlur: true)
            ->afterStateUpdated(fn ($livewire) => $livewire->validateOnly('data.' . $prefix . 'user_id'))
            ->allowHtml()
            ->options(fn () => User::query()->orderBy('name')->limit(10)->get()->mapWithKeys(function ($user) {
                return [$user->getKey() => static::formatOptionWithIcon($user->name, $user->image)];
            })->toArray())
            ->getSearchResultsUsing(function (string $search) {
                $users = User::where('name', 'like', "%{$search}%")->limit(10)->get();

                return $users->mapWithKeys(function ($user) {
                    return [$user->getKey() => static::formatOptionWithIcon($user->name, $user->image)];
                })->toArray();
            })
            ->getOptionLabelUsing(function ($value): string {
                $user = User::find($value);

                return static::formatOptionWithIcon($user->name, $user->image);
            })
            ->searchable()
            ->selectablePlaceholder(false);

        static::applyExpenseFieldConditions($userField, $forExpense, $isCurrentField);

        if ($forExpense) {
            $dateField
                ->minDate(now()->startOfMonth())
                ->default(now());

            $userField
                ->default(Auth::id());
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
                        'data.' . $prefix . 'category_id' => 'required',
                    ]);
                }

                $livewire->validateOnly('data.' . $prefix . 'category_id');
            })
            ->allowHtml()
            ->options(fn () => Category::query()->orderBy('name')->limit(10)->get()->mapWithKeys(function ($category) {
                return [$category->getKey() => static::formatOptionWithIcon($category->name, $category->image)];
            })->toArray())
            ->getSearchResultsUsing(function (string $search) {
                $categories = Category::where('name', 'like', "%{$search}%")->limit(10)->get();

                return $categories->mapWithKeys(function ($category) {
                    return [$category->getKey() => static::formatOptionWithIcon($category->name, $category->image)];
                })->toArray();
            })
            ->getOptionLabelUsing(function ($value): string {
                $category = Category::find($value);

                return static::formatOptionWithIcon($category->name, $category->image);
            })
            ->searchable();

        static::applyExpenseFieldConditions($categoryField, $forExpense, $isCurrentField);

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

                return $query->orderBy('name')->limit(10)->get()->mapWithKeys(function ($supplier) {
                    return [$supplier->getKey() => static::formatOptionWithIcon($supplier->name, $supplier->image)];
                })->toArray();
            })
            ->getSearchResultsUsing(function (string $search, $get) use ($prefix) {
                $query = Supplier::query()->where('name', 'like', "%{$search}%");

                if (filled($get($prefix . 'category_id'))) {
                    $query->where('category_id', $get($prefix . 'category_id'));
                }

                return $query->limit(10)->get()->mapWithKeys(function ($supplier) {
                    return [$supplier->getKey() => static::formatOptionWithIcon($supplier->name, $supplier->image)];
                })->toArray();
            })
            ->getOptionLabelUsing(function ($value): string {
                $supplier = Supplier::find($value);

                return static::formatOptionWithIcon($supplier->name, $supplier->image);
            })
            ->searchable()
            ->selectablePlaceholder(false);

        static::applyExpenseFieldConditions($supplierField, $forExpense, $isCurrentField);

        $sumField = TextInput::make($prefix . 'sum')
            ->label(__('resources.fields.sum'))
            ->suffix('MDL')
            ->numeric()
            ->placeholder('0.00');

        static::applyExpenseFieldConditions($sumField, $forExpense, $isCurrentField);

        $notesField = MarkdownEditor::make($prefix . 'notes')
            ->label(__('resources.fields.notes'))
            ->columnSpanFull()
            ->disableToolbarButtons([
                'attachFiles',
                'blockquote',
                'codeBlock',
                'heading',
                'table',
                'link',
            ]);

        if ($forExpense) {
            $notesField->hintAction(static::makeNotesVoiceAction());
        }

        static::applyExpenseFieldConditions($notesField, $forExpense, $isCurrentField);

        return [
            TextInput::make('id')
                ->label(__('resources.fields.id'))
                ->disabled()
                ->visible(fn (?Model $record) => $record !== null && $record instanceof Expense)
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

    protected static function makeExpenseVoiceAction(): Action
    {
        return Action::make('voice_expense_input')
            ->label(__('resources.buttons.voice_expense'))
            ->icon('heroicon-o-microphone')
            ->color('info')
            ->visible(fn (string $operation): bool => in_array($operation, ['create', 'edit'], true))
            ->modal()
            ->modalIcon('heroicon-o-microphone')
            ->modalHeading(__('resources.voice.expense.modal_heading'))
            ->modalDescription(__('resources.voice.expense.modal_description'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->stickyModalHeader()
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->closeModalByClickingAway(true)
            ->closeModalByEscaping(true)
            ->modalContent(fn (): View => view('filament.expenses.voice-input-modal', [
                'mode' => 'expense',
                'transcriptionUrl' => route('expense-voice.transcribe'),
            ]));
    }

    protected static function makeNotesVoiceAction(): Action
    {
        return Action::make('voice_notes_input')
            ->label(__('resources.buttons.voice_notes'))
            ->tooltip(__('resources.voice.notes.tooltip'))
            ->icon('heroicon-o-microphone')
            ->color('gray')
            ->iconButton()
            ->visible(fn (string $operation): bool => in_array($operation, ['create', 'edit'], true))
            ->modal()
            ->modalIcon('heroicon-o-microphone')
            ->modalHeading(__('resources.voice.notes.modal_heading'))
            ->modalDescription(__('resources.voice.notes.modal_description'))
            ->modalWidth(Width::TwoExtraLarge)
            ->stickyModalHeader()
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->closeModalByClickingAway(true)
            ->closeModalByEscaping(true)
            ->modalContent(fn (): View => view('filament.expenses.voice-input-modal', [
                'mode' => 'notes',
                'transcriptionUrl' => route('expense-voice.transcribe'),
            ]));
    }
}