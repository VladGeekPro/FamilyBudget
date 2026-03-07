<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseChangeRequestResource\Pages;
use App\Models\Expense;
use App\Models\ExpenseChangeRequest;
use App\Models\ExpenseChangeRequestVote;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Filament\Resources\Base\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\Width;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

class ExpenseChangeRequestResource extends BaseResource
{
    protected static ?string $model = ExpenseChangeRequest::class;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Транзакции';
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?string $navigationLabel = 'Запросы';

    protected static ?string $modelLabel = 'запрос';

    protected static ?string $pluralModelLabel = 'запросы';

    protected static ?int $navigationSort = 4;

    protected static ?string $defaultSortColumn = 'created_at';

    protected static string  $defaultSortDirection = 'desc';

    public static function getNavigationBadge(): ?string
    {

        $unansweredRecords = ExpenseChangeRequest::Unanswered()->get();

        if ($unansweredRecords->isEmpty()) {
            return null;
        }

        $badgeMessage = "";
        foreach ($unansweredRecords as $record) {
            $icon = User::getIcon($record->email);
            $badgeMessage .= "{$record->unanswered_count} {$icon}";
        }

        return trim($badgeMessage);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected static function getFieldDisplay(ExpenseChangeRequest $record, string $field): array
    {
        $fieldNames = [
            'notes' => 'Комментаррии',
        ];

        $currentValue = null;
        $requestedValue = null;

        switch ($field) {
            case 'user':
                $currentValue = $record->currentUser?->name;
                $requestedValue = $record->requestedUser?->name;
                break;
            case 'date':
                $currentValue = $record->current_date?->format('d.m.Y');
                $requestedValue = $record->requested_date ? \Carbon\Carbon::parse($record->requested_date)->format('d.m.Y') : null;
                break;
            case 'category':
                $currentValue = $record->currentCategory?->name;
                $requestedValue = $record->requestedCategory?->name;
                break;
            case 'supplier':
                $currentValue = $record->currentSupplier?->name;
                $requestedValue = $record->requestedSupplier?->name;
                break;
            case 'sum':
                $currentValue = $record->current_sum ? number_format($record->current_sum, 2) . ' MDL' : null;
                $requestedValue = $record->requested_sum ? number_format($record->requested_sum, 2) . ' MDL' : null;
                break;
            case 'notes':
                $currentValue = $record->current_notes;
                $requestedValue = $record->requested_notes;
                break;
        }

        $fieldName = $fieldNames[$field] ?? $field;

        return [
            'action_type' => $record->action_type,
            'current' => $currentValue ?? "Поле \"{$fieldName}\" не заполнено",
            'requested' => $requestedValue ?? "Поле \"{$fieldName}\" не заполнено",
            'changed' => $currentValue !== $requestedValue,
        ];
    }

    protected static function getFieldColor(ExpenseChangeRequest $record, string $field): string
    {

        if ($record->action_type === 'delete') {
            return 'danger';
        } elseif ($record->action_type === 'create') {
            return 'success';
        } else {

            $hasChange = false;

            switch ($field) {
                case 'user':
                    $hasChange = $record->requested_user_id != $record->current_user_id;
                    break;
                case 'date':
                    $hasChange = $record->requested_date != $record->current_date;
                    break;
                case 'category':
                    $hasChange = $record->requested_category_id != $record->current_category_id;
                    break;
                case 'supplier':
                    $hasChange = $record->requested_supplier_id != $record->current_supplier_id;
                    break;
                case 'sum':
                    $hasChange = $record->requested_sum != $record->current_sum;
                    break;
                case 'notes':
                    $hasChange = $record->requested_notes != $record->current_notes;
                    break;
            }

            return $hasChange ? 'warning' : 'gray';
        }
    }

    public static function fillExpenseFields($expenseId, $actionType, callable $set): void
    {

        $expense = Expense::find($expenseId);

        $fields = [
            'user_id' => $expense->user_id,
            'date' => $expense->date->format('Y-m-d'),
            'category_id' => $expense->category_id,
            'supplier_id' => $expense->supplier_id,
            'sum' => $expense->sum,
            'notes' => $expense->notes,
        ];

        foreach ($fields as $field => $value) {
            $set("current_{$field}", $value);

            if ($actionType === 'edit' || $actionType === 'create') {
                $set("requested_{$field}", $value);
            }
        }
    }

    public static function getVoteForm(): array
    {
        return [
            Forms\Components\Radio::make('vote')
                ->label(__('resources.radio_buttons.vote.title'))
                ->options(__('resources.radio_buttons.vote.options'))
                ->inline()
                ->required()
                ->extraAttributes(['class' => 'ml-auto']),

            Forms\Components\Textarea::make('notes')
                ->label(__('resources.fields.vote_comment')),

            \Filament\Schemas\Components\Group::make([
                Forms\Components\Placeholder::make('confirm_vote_notice')
                    ->hiddenLabel()
                    ->content(new HtmlString('
                    <p class="text-sm font-semibold text-amber-900">Подтверждение голоса</p>
                    <p class="mt-1 text-xs text-amber-800">
                        Голос нельзя будет изменить после отправки. Если комментарий заполнен, проверьте его перед подтверждением.
                    </p>
                ')),

                Forms\Components\Checkbox::make('confirm_vote')
                    ->label(__('resources.buttons.confirm_vote'))
                    ->accepted()
                    ->required(),
            ])->extraAttributes([
                'class' => 'rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 space-y-3',
            ]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([

                \Filament\Schemas\Components\Section::make(__('resources.sections.user_votes'))
                    ->columnSpanFull()
                    ->headerActions([
                        FormAction::make('vote')
                            ->label(__('resources.buttons.vote'))
                            ->icon('heroicon-o-hand-thumb-up')
                            ->color(fn(?ExpenseChangeRequest $record): string => !$record || $record->hasUserVoted(auth()->user()) ? 'gray' : 'primary')
                            ->disabled(fn(?ExpenseChangeRequest $record): bool => !$record || $record->hasUserVoted(auth()->user()))
                            ->form(static::getVoteForm())
                            ->modalFooterActions(fn(FormAction $action): array => [
                                $action->getModalCancelAction(),
                                $action->getModalSubmitAction()
                                    ->label(__('resources.buttons.vote'))
                                    ->icon('heroicon-o-hand-thumb-up')
                                    ->extraAttributes(['class' => 'ml-auto']),
                            ])
                            ->action(function (?ExpenseChangeRequest $record, array $data): void {
                                ExpenseChangeRequestVote::vote(
                                    $record->id,
                                    auth()->id(),
                                    $data['vote'],
                                    $data['notes'],
                                );

                                Notification::make()
                                    ->title(__('resources.notifications.warn.vote.title'))
                                    ->body(__('resources.notifications.warn.vote.body.' . $data['vote']))
                                    ->success()
                                    ->send();
                            })

                    ])
                    ->schema([
                        \Filament\Schemas\Components\View::make('filament.forms.components.view-votes')
                            ->columnSpanFull(),
                    ])->visible(fn(string $operation) => in_array($operation, ['edit', 'view'])),

                Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('action_type')
                            ->label(__('resources.fields.action_type.label'))
                            ->options(__('resources.fields.action_type.options'))
                            ->selectablePlaceholder(false)
                            ->default('edit')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {

                                $expense = $get('expense_id') && $get('action_type') !== 'create' ? \App\Models\Expense::find($get('expense_id')) : null;

                                $fields = [
                                    'user_id' => $expense?->user_id,
                                    'date' => $expense?->date?->format('Y-m-d'),
                                    'category_id' => $expense?->category_id,
                                    'supplier_id' => $expense?->supplier_id,
                                    'sum' => $expense?->sum,
                                    'notes' => $expense?->notes,
                                ];

                                foreach ($fields as $field => $value) {
                                    if ($get('action_type') === 'delete') {
                                        $set("current_{$field}", $value);
                                        $set("requested_{$field}", null);
                                    } else {
                                        $set("current_{$field}", $value);
                                        $set("requested_{$field}", $value);
                                    }

                                    if ($get('action_type') === 'create') {
                                        $set('expense_id', null);
                                        $set('requested_user_id', auth()->id());
                                    }
                                }
                            }),

                        Forms\Components\Select::make('expense_id')
                            ->label(__('resources.fields.change_expense'))
                            ->live()
                            ->afterStateUpdated(function ($livewire, $state, Set $set, Get $get) {
                                if ($get('action_type') === 'create') {
                                    return;
                                }
                                $livewire->dispatch('expense:selected', expenseId: $state);
                                $livewire->validateOnly('expense_id');
                            })
                            ->searchable()
                            ->preload()
                            ->options(fn() => Expense::previousMonthsExpenses()
                                ->orderBy('id', 'desc')
                                ->limit(100)
                                ->get()
                                ->mapWithKeys(fn($expense) => [
                                    $expense->id => "#{$expense->id} - {$expense->supplier->name} - {$expense->sum} MDL ({$expense->date->format('d.m.Y')})",
                                ]))
                            ->getSearchResultsUsing(function (string $search) {

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
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($expense) {
                                        return [$expense->id => "#{$expense->id} - {$expense->supplier->name} - {$expense->sum} MDL ({$expense->date->format('d.m.Y')})"];
                                    });
                            })
                            ->getOptionLabelUsing(function ($value): string {
                                $expense = Expense::find($value);
                                return "#{$expense->id} - {$expense->supplier->name} - {$expense->sum} MDL ({$expense->date->format('d.m.Y')})";
                            })
                            ->prefixAction(
                                FormAction::make('select_expense')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->modalHeading('')
                                    ->modalWidth(Width::SevenExtraLarge)
                                    ->modalContent(fn() => view('filament.resources.expense-change-request.select-expense-table'))
                                    ->modalSubmitAction(false)
                                    ->modalCancelAction(false)
                                    ->slideOver()
                                    ->disabled(fn(string $operation) => $operation === 'view')
                            )
                            ->required(fn($get) => $get('action_type') !== 'create')
                            ->visible(fn($get) => $get('action_type') !== 'create')
                            ->dehydrated(fn($get) => $get('action_type') !== 'create'),

                    ])->columnSpanFull(),

                Forms\Components\Textarea::make('notes')
                    ->label(__('resources.fields.change_reason'))
                    ->required()
                    ->columnSpanFull(),

                \Filament\Schemas\Components\Section::make('Сравнение данных')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                \Filament\Schemas\Components\Section::make('Текущее значение')
                                    ->schema(
                                        static::getExpenseFormFields('current_', false)
                                    )
                                    ->columnSpan(fn($get) => $get('action_type') !== 'delete' ? 1 : 2)
                                    ->extraAttributes(['class' => 'h-full'])
                                    ->visible(fn($get) => $get('action_type') !== 'create'),

                                \Filament\Schemas\Components\Section::make('Новое значение')
                                    ->schema(
                                        static::getExpenseFormFields('requested_', false)
                                    )
                                    ->columnSpan(fn($get) => $get('action_type') !== 'create' ? 1 : 2)
                                    ->visible(fn($get) => $get('action_type') !== 'delete'),
                            ])
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {

        $table = parent::table($table);

        return $table
            ->columns([
                TableGrid::make(['default' => 2])
                    ->schema([
                        Tables\Columns\TextColumn::make('created_at')
                            ->dateTime('d M. Y H:i')
                            ->color('info'),
                        Tables\Columns\ImageColumn::make('user.image')
                            ->circular()
                            ->imageHeight(40)
                            ->imageWidth(40)
                            ->extraAttributes(['class' => 'justify-end']),
                    ])->extraAttributes(['class' => 'items-center']),

                Panel::make([

                    Split::make([
                        Tables\Columns\IconColumn::make('action_type')
                            ->label('')
                            ->icon(fn(string $state): string => match ($state) {
                                'create' => 'heroicon-o-plus-circle',
                                'edit' => 'heroicon-o-pencil-square',
                                'delete' => 'heroicon-o-trash',
                                default => 'heroicon-o-question-mark-circle',
                            })
                            ->color(fn(string $state): string => match ($state) {
                                'create' => 'success',
                                'edit' => 'warning',
                                'delete' => 'danger',
                                default => 'gray',
                            })
                            ->size('md')
                            ->grow(false),

                        Tables\Columns\TextColumn::make('action_type_label')
                            ->state(fn($record) => __('resources.fields.action_type.options')[$record->action_type])
                            ->color(fn($record): string => match ($record->action_type) {
                                'create' => 'success',
                                'edit' => 'warning',
                                'delete' => 'danger',
                                default => 'gray',
                            }),
                    ])->extraAttributes(['class' => 'mb-2']),

                    Stack::make([

                        Tables\Columns\ViewColumn::make('date_display')
                            ->view('filament.tables.columns.change-field')
                            ->state(fn(ExpenseChangeRequest $record) => static::getFieldDisplay($record, 'date')),

                        Tables\Columns\ViewColumn::make('user_display')
                            ->view('filament.tables.columns.change-field')
                            ->state(fn(ExpenseChangeRequest $record) => static::getFieldDisplay($record, 'user')),

                        Tables\Columns\ViewColumn::make('category_display')
                            ->view('filament.tables.columns.change-field')
                            ->state(fn(ExpenseChangeRequest $record) => static::getFieldDisplay($record, 'category')),

                        Tables\Columns\ViewColumn::make('supplier_display')
                            ->view('filament.tables.columns.change-field')
                            ->state(fn(ExpenseChangeRequest $record) => static::getFieldDisplay($record, 'supplier')),

                        Tables\Columns\ViewColumn::make('sum_display')
                            ->view('filament.tables.columns.change-field')
                            ->state(fn(ExpenseChangeRequest $record) => static::getFieldDisplay($record, 'sum')),

                        Tables\Columns\ViewColumn::make('notes_display')
                            ->view('filament.tables.columns.change-field')
                            ->state(fn(ExpenseChangeRequest $record) => static::getFieldDisplay($record, 'notes')),
                    ])->space(3),
                ])->extraAttributes(['class' => 'my-2']),

                Split::make([
                    Tables\Columns\BadgeColumn::make('status')
                        ->label(__('resources.fields.status'))
                        ->colors([
                            'warning' => 'pending',
                            'success' => 'completed',
                            'danger' => 'rejected',
                        ])
                        ->formatStateUsing(fn($state) => __('resources.toggle_buttons.vote_status.' . $state)),

                    Tables\Columns\TextColumn::make('votes_summary')
                        ->getStateUsing(function (ExpenseChangeRequest $record) {
                            $approved = $record->getApprovedVotesCount();
                            $rejected = $record->getRejectedVotesCount();
                            $total = User::count();
                            $pending = $total - $approved - $rejected;

                            return "✅ {$approved} | ❌ {$rejected} | ⏳ {$pending}";
                        })->extraAttributes(['class' => 'text-end']),
                ]),

            ])
            ->contentGrid([
                'md' => 2,
                'lg' => 1,
                'xl' => 2,
                '2xl' => 3,
            ])
            ->recordClasses('expense-change-request-record')
            ->filters(static::getExpenseChangeRequestTableFilters())->filtersFormWidth(Width::Small)
            ->recordActions([
                \Filament\Actions\ViewAction::make()
                    ->extraAttributes(['class' => 'mr-auto']),

                Action::make('view_votes')
                    ->label(__('resources.buttons.votes'))
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->schema(fn(ExpenseChangeRequest $record): array => [

                        Section::make(__('resources.sections.user_votes'))
                            ->schema([
                                \Filament\Schemas\Components\View::make('filament.forms.components.view-votes')
                                    ->viewData([
                                        'getRecord' => function () use ($record) {
                                            return $record;
                                        },
                                    ])
                            ]),

                        Section::make(__('resources.sections.to_vote'))
                            ->schema(static::getVoteForm())
                            ->visible(fn(ExpenseChangeRequest $record) => !$record->hasUserVoted(auth()->user())),
                    ])
                    ->action(function (ExpenseChangeRequest $record, array $data) {

                        ExpenseChangeRequestVote::vote(
                            $record->id,
                            auth()->user()->id,
                            $data['vote'],
                            $data['notes']
                        );

                        Notification::make()
                            ->title(__('resources.notifications.warn.vote.title'))
                            ->body(__('resources.notifications.warn.vote.body.' . $data['vote']))
                            ->success()
                            ->send();
                    })
                    ->modalFooterActions(fn(Action $action): array => [
                        $action->getModalCancelAction(),
                        $action->getModalSubmitAction(),
                    ])
                    ->modalSubmitAction(function (Action $action, ExpenseChangeRequest $record) {
                        return $action
                            ->label(__('resources.buttons.vote'))
                            ->icon('heroicon-o-hand-thumb-up')
                            ->disabled(fn() => $record->hasUserVoted(auth()->user()))
                            ->color(fn() => $record->hasUserVoted(auth()->user()) ? 'gray' : 'primary')
                            ->extraAttributes(['class' => 'ml-auto']);
                    })
                    ->modalHeading(__('resources.sections.voting'))
                    ->modalWidth(Width::TwoExtraLarge),

                \Filament\Actions\EditAction::make()
                    ->visible(fn(ExpenseChangeRequest $record) => $record->status === 'pending'),
            ]);
    }

    protected static function getExpenseChangeRequestTableFilters(): array
    {
        return array_merge(
            [
                static::makeRelationshipFilter('user', __('resources.fields.user'), 'user'),
            ],
            [
                static::makeSelectOptionsFilter(
                    'status',
                    __('resources.fields.status'),
                    __('resources.toggle_buttons.change_request_status'),
                ),
                static::makeSelectOptionsFilter(
                    'action_type',
                    'Тип операции',
                    __('resources.fields.action_type.notification_options'),
                ),
                static::makeDateRangeFilter('created_at', 'resources.fields.date', 'created_at'),
            ],
        );
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseChangeRequests::route('/'),
            'create' => Pages\CreateExpenseChangeRequest::route('/create'),
            'view' => Pages\ViewExpenseChangeRequest::route('/{record}'),
            'edit' => Pages\EditExpenseChangeRequest::route('/{record}/edit'),
        ];
    }
}
