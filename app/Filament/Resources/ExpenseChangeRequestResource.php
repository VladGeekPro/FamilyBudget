<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseChangeRequestResource\Pages;
use App\Models\Expense;
use App\Models\ExpenseChangeRequest;
use App\Models\ExpenseChangeRequestVote;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\Base\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;

class ExpenseChangeRequestResource extends BaseResource
{
    protected static ?string $model = ExpenseChangeRequest::class;

    protected static ?string $navigationGroup = 'Транзакции';

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?string $navigationLabel = 'Запросы на изменения';

    protected static ?string $modelLabel = 'запрос на изменение';

    protected static ?string $pluralModelLabel = 'запросы на изменения';

    protected static ?int $navigationSort = 4;

    protected static function getFieldDisplay(ExpenseChangeRequest $record, string $field): array
    {
        $oldValue = null;
        $newValue = null;

        switch ($field) {
            case 'user':
                $oldValue = $record->expense?->user?->name;
                $newValue = $record->requestedUser?->name;
                break;
            case 'date':
                $oldValue = $record->expense?->date?->format('d.m.Y');
                $newValue = $record->requested_date ? \Carbon\Carbon::parse($record->requested_date)->format('d.m.Y') : null;
                break;
            case 'category':
                $oldValue = $record->expense?->category?->name;
                $newValue = $record->requestedCategory?->name;
                break;
            case 'supplier':
                $oldValue = $record->expense?->supplier?->name;
                $newValue = $record->requestedSupplier?->name;
                break;
            case 'sum':
                $oldValue = $record->expense?->sum ? number_format($record->expense->sum, 2) . ' MDL' : null;
                $newValue = $record->requested_sum ? number_format($record->requested_sum, 2) . ' MDL' : null;
                break;
            case 'notes':
                $oldValue = $record->expense?->notes;
                $newValue = $record->requested_notes;
                break;
        }

        return [
            'action_type' => $record->action_type,
            'old' => $oldValue ?? 'Не указано',
            'new' => $newValue ?? 'Не указано',
            'changed' => $oldValue !== $newValue,
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
                    $hasChange = $record->requested_user_id != $record->expense->user_id;
                    break;
                case 'date':
                    $hasChange = $record->requested_date != $record->expense->date;
                    break;
                case 'category':
                    $hasChange = $record->requested_category_id != $record->expense->category_id;
                    break;
                case 'supplier':
                    $hasChange = $record->requested_supplier_id != $record->expense->supplier_id;
                    break;
                case 'sum':
                    $hasChange = $record->requested_sum != $record->expense->sum;
                    break;
                case 'notes':
                    $hasChange = $record->requested_notes != $record->expense->notes;
                    break;
            }

            return $hasChange ? 'warning' : 'gray';
        }
    }

    protected static function fillExpenseFields($expenseId, $actionType, callable $set): void
    {
        if (!$expenseId) {
            return;
        }

        $expense = Expense::find($expenseId);
        
        if (!$expense) {
            return;
        }

        $fields = [
            'user_id' => $expense->user_id,
            'date' => $expense->date?->format('Y-m-d'),
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Section::make('Информация о запросе')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label(__('resources.fields.user'))
                            ->relationship('user', 'name')
                            ->default(auth()->id())
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options([
                                'pending' => 'Ожидает голосования',
                                'completed' => 'Завершён',
                                'rejected' => 'Отклонён',
                            ])
                            ->disabled()
                            ->default('pending'),

                        Forms\Components\DateTimePicker::make('applied_at')
                            ->label(__('resources.fields.applied_at'))
                            ->disabled(),
                    ])
                    ->columns(3)
                    ->visible(fn(string $operation) => $operation === 'edit'),

                Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('action_type')
                            ->label(__('resources.fields.action_type.label'))
                            ->options(__('resources.fields.action_type.options'))
                            ->selectablePlaceholder(false)
                            ->default('edit')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {

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
                            ->afterStateUpdated(function ($livewire, $state, Forms\Set $set, $get) {
                                if ($get('action_type') === 'create') {
                                    return;
                                }
                                $livewire->validateOnly('expense_id');
                                static::fillExpenseFields($state, $get('action_type'), $set);
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
                            ->prefixAction(
                                FormAction::make('select_expense')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->modalHeading('')
                                    ->modalWidth(MaxWidth::SevenExtraLarge)
                                    ->modalContent(fn() => view('filament.resources.expense-change-request.select-expense-table'))
                                    ->modalSubmitAction(false)
                                    ->modalCancelAction(false)
                                    ->slideOver()
                            )
                            ->required(fn($get) => $get('action_type') !== 'create')
                            ->visible(fn($get) => $get('action_type') !== 'create')
                            ->dehydrated(fn($get) => $get('action_type') !== 'create'),

                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label(__('resources.fields.change_reason'))
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Section::make('Сравнение данных')
                    ->description('Текущие значения и новые значения для сравнения')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Section::make('Текущее значение')
                                    ->schema(
                                        static::getExpenseFormFields('current_', false, false)
                                    )
                                    ->columnSpan(1),

                                Forms\Components\Section::make('Новое значение')
                                    ->schema(
                                        static::getExpenseFormFields('requested_', false, true)
                                    )
                                    ->columnSpan(1),
                            ])
                    ])
                    ->visible(fn($get, string $operation) => $operation === 'create' || $operation === 'view' || $operation === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TableGrid::make(['default' => 2])
                    ->schema([
                        Tables\Columns\TextColumn::make('created_at')
                            ->label('Создан')
                            ->dateTime('d M. Y H:i')
                            ->color('info'),
                        Tables\Columns\ImageColumn::make('user.image')
                            ->circular()
                            ->height(40)
                            ->width(40)
                            ->extraAttributes(['style' => 'margin-left:auto;']),
                    ]),

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
                        ->label('Статус')
                        ->colors([
                            'warning' => 'pending',
                            'success' => 'completed',
                            'danger' => 'rejected',
                        ])
                        ->formatStateUsing(fn(string $state): string => match ($state) {
                            'pending' => 'Ожидает',
                            'completed' => 'Завершён',
                            'rejected' => 'Отклонён',
                            default => $state,
                        }),

                    Tables\Columns\TextColumn::make('votes_summary')
                        ->label('Голоса')
                        ->getStateUsing(function (ExpenseChangeRequest $record) {
                            $approved = $record->votes()->where('vote', 'approved')->count();
                            $rejected = $record->votes()->where('vote', 'rejected')->count();
                            $total = User::count();
                            $pending = $total - $approved - $rejected;

                            return "✅ {$approved} | ❌ {$rejected} | ⏳ {$pending}";
                        })->extraAttributes(['class' => 'justify-end']),
                ]),

            ])
            ->contentGrid([
                'md' => 2,
                'lg' => 1,
                'xl' => 2,
                '2xl' => 3,
            ])
            ->recordClasses('expense-change-request-record')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидает голосования',
                        'completed' => 'Завершён',
                        'rejected' => 'Отклонён',
                    ]),

                Tables\Filters\SelectFilter::make('action_type')
                    ->label('Тип операции')
                    ->options([
                        'create' => 'Создание',
                        'edit' => 'Редактирование',
                        'delete' => 'Удаление',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Автор')
                    ->relationship('user', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalWidth(MaxWidth::FourExtraLarge),

                Action::make('vote')
                    ->label('Голосовать')
                    ->icon('heroicon-o-hand-thumb-up')
                    ->color('primary')
                    ->visible(function (ExpenseChangeRequest $record) {
                        $user = auth()->user();
                        return $record->status === 'pending' && !$record->hasUserVoted($user);
                    })
                    ->form([
                        Forms\Components\Radio::make('vote_decision')
                            ->label('Ваше решение')
                            ->options([
                                'approve' => 'Одобрить изменения',
                                'reject' => 'Отклонить изменения',
                            ])
                            ->required()
                            ->inline(),

                        Forms\Components\Textarea::make('vote_comment')
                            ->label('Комментарий (необязательно)')
                            ->maxLength(500),
                    ])
                    ->action(function (ExpenseChangeRequest $record, array $data) {
                        $approved = $data['vote_decision'] === 'approve';

                        ExpenseChangeRequestVote::vote(
                            $record,
                            auth()->user(),
                            $approved,
                            $data['vote_comment'] ?? null
                        );

                        Notification::make()
                            ->title('Голос учтён')
                            ->body($approved ? 'Вы одобрили изменения' : 'Вы отклонили изменения')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Голосование за изменения')
                    ->modalDescription('Внимательно ознакомьтесь с предлагаемыми изменениями перед голосованием'),

                Action::make('view_votes')
                    ->label('Голоса')
                    ->icon('heroicon-o-users')
                    ->color('gray')
                    ->modalContent(function (ExpenseChangeRequest $record) {
                        $votes = $record->getAllVotes();
                        $pendingUsers = $record->getPendingUsers();

                        return view('filament.resources.expense-change-request.view-votes', [
                            'votes' => $votes,
                            'pendingUsers' => $pendingUsers,
                        ]);
                    })
                    ->modalWidth(MaxWidth::TwoExtraLarge),

                Tables\Actions\EditAction::make()
                    ->visible(fn(ExpenseChangeRequest $record) => $record->status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete_any_expense_change_request')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['expense.supplier', 'user', 'votes.user']);
    }
}
