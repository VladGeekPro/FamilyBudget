<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseChangeRequestResource\Pages;
use App\Models\Expense;
use App\Models\ExpenseChangeRequest;
use App\Models\ExpenseChangeRequestVote;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Components\Grid;

class ExpenseChangeRequestResource extends Resource
{
    protected static ?string $model = ExpenseChangeRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Запросы на изменения';

    protected static ?string $modelLabel = 'Запрос на изменение';

    protected static ?string $pluralModelLabel = 'Запросы на изменения';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('action_type')
                            ->label(__('resources.fields.action_type.label'))
                            ->options(__('resources.fields.action_type.options'))
                            ->selectablePlaceholder(false)
                            ->default('edit')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state === 'create') {
                                    $set('expense_id', null);
                                    $set('requested_user_id', auth()->id());
                                    $set('requested_date', null);
                                    $set('requested_category_id', null);
                                    $set('requested_supplier_id', null);
                                    $set('requested_sum', null);
                                    $set('requested_notes', null);
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
                                if ($state) {
                                    $expense = \App\Models\Expense::find($state);
                                    if ($expense) {
                                        $set('requested_user_id', $expense->user_id);
                                        $set('requested_date', $expense->date?->format('Y-m-d'));
                                        $set('requested_category_id', $expense->category_id);
                                        $set('requested_supplier_id', $expense->supplier_id);
                                        $set('requested_sum', $expense->sum);
                                        $set('requested_notes', $expense->notes);
                                    }
                                }
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
                                return \App\Models\Expense::query()
                                    ->where(function ($query) use ($search) {
                                        $query->whereHas('supplier', function ($q) use ($search) {
                                            $q->where('name', 'like', "%{$search}%");
                                        })
                                            ->orWhere('notes', 'like', "%{$search}%")
                                            ->orWhere('sum', 'like', "%{$search}%");
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
                            )
                            ->required(fn($get) => $get('action_type') !== 'create')
                            ->visible(fn(string $operation, $get) => $operation === 'create' && $get('action_type') !== 'create')
                            ->dehydrated(fn($get) => $get('action_type') !== 'create'),

                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label(__('resources.fields.change_reason'))
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Section::make(__('resources.sections.change_data'))
                    ->description(__('resources.fields.change_data_description'))
                    ->schema([
                        Forms\Components\Select::make('requested_user_id')
                            ->label(__('resources.fields.payer'))
                            ->relationship('requestedUser', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\DatePicker::make('requested_date')
                            ->label(__('resources.fields.date'))
                            ->displayFormat('d.m.Y'),

                        Forms\Components\Select::make('requested_category_id')
                            ->label(__('resources.fields.category'))
                            ->relationship('requestedCategory', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('resources.fields.name.inanimate'))
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('requested_supplier_id')
                            ->label(__('resources.fields.supplier'))
                            ->relationship('requestedSupplier', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('resources.fields.name.inanimate'))
                                    ->required(),
                            ]),

                        Forms\Components\TextInput::make('requested_sum')
                            ->label(__('resources.fields.sum'))
                            ->numeric()
                            ->step(0.01)
                            ->placeholder('0.00'),

                        Forms\Components\Textarea::make('requested_notes')
                            ->label(__('resources.fields.notes'))
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('action_type')
                    ->label('Тип')
                    ->colors([
                        'success' => 'create',
                        'warning' => 'edit',
                        'danger' => 'delete',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'create' => 'Создать',
                        'edit' => 'Изменить',
                        'delete' => 'Удалить',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('expense.supplier.name')
                    ->label('Поставщик')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(function (ExpenseChangeRequest $record) {
                        // Показываем оригинальный поставщик или запрашиваемый
                        if ($record->expense && $record->expense->supplier) {
                            return $record->expense->supplier->name;
                        }
                        if ($record->requestedSupplier) {
                            return $record->requestedSupplier->name . ' (новый)';
                        }
                        return 'Не указан';
                    }),

                Tables\Columns\TextColumn::make('sum_display')
                    ->label('Сумма')
                    ->getStateUsing(function (ExpenseChangeRequest $record) {
                        if ($record->expense && $record->expense->sum) {
                            $original = number_format($record->expense->sum, 2) . ' MDL';
                            if ($record->requested_sum && $record->requested_sum != $record->expense->sum) {
                                return $original . ' → ' . number_format($record->requested_sum, 2) . ' MDL';
                            }
                            return $original;
                        }
                        if ($record->requested_sum) {
                            return number_format($record->requested_sum, 2) . ' MDL (новая)';
                        }
                        return 'Не указана';
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Автор')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('changes_summary')
                    ->label('Изменения')
                    ->getStateUsing(function (ExpenseChangeRequest $record) {
                        $changes = [];

                        if ($record->action_type === 'create') {
                            return 'Создание нового расхода';
                        }

                        if ($record->action_type === 'delete') {
                            return 'Удаление расхода';
                        }

                        // Для редактирования показываем что именно меняется
                        if ($record->expense) {
                            if ($record->requested_user_id && $record->requested_user_id != $record->expense->user_id) {
                                $changes[] = 'Плательщик';
                            }
                            if ($record->requested_date && $record->requested_date != $record->expense->date) {
                                $changes[] = 'Дата';
                            }
                            if ($record->requested_category_id && $record->requested_category_id != $record->expense->category_id) {
                                $changes[] = 'Категория';
                            }
                            if ($record->requested_supplier_id && $record->requested_supplier_id != $record->expense->supplier_id) {
                                $changes[] = 'Поставщик';
                            }
                            if ($record->requested_sum !== null && $record->requested_sum != $record->expense->sum) {
                                $changes[] = 'Сумма';
                            }
                            if ($record->requested_notes !== null && $record->requested_notes != $record->expense->notes) {
                                $changes[] = 'Заметки';
                            }
                        }

                        return !empty($changes) ? implode(', ', $changes) : 'Без изменений';
                    })
                    ->wrap(),

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
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('applied_at')
                    ->label('Применён')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
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
