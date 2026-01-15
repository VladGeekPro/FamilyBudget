<?php

namespace App\Filament\Resources\ExpenseChangeRequestResource\Pages;

use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\Expense;
use App\Models\User;
use App\Notifications\ExpenseChangeRequestCreated;
use Filament\Actions;
use App\Filament\Resources\Base\CreateBase;
use Filament\Notifications\Notification;

class CreateExpenseChangeRequest extends CreateBase
{
    protected static string $resource = ExpenseChangeRequestResource::class;

    #[\Livewire\Attributes\On('expense:selected')]
    public function selectExpense($expenseId)
    {
        $this->data['expense_id'] = $expenseId;
        
        // Используем общий метод для заполнения полей
        $actionType = $this->data['action_type'];
        ExpenseChangeRequestResource::fillExpenseFields(
            $expenseId, 
            $actionType, 
            fn($key, $value) => $this->data[$key] = $value
        );
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        // Для демонстрации - заполняем change_data
        if ($data['action_type'] === 'create') {
            $data['change_data'] = [
                'new' => $data['change_data'] ?? []
            ];
        } else {
            // Для edit/delete получаем текущие данные expense
            if (isset($data['expense_id'])) {
                $expense = \App\Models\Expense::find($data['expense_id']);
                $data['change_data'] = [
                    'old' => $expense?->toArray() ?? [],
                    'new' => [] // Заполнится через отдельную форму
                ];
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $request = $this->record;

        // Отправляем уведомления всем пользователям
        $users = User::all();
        foreach ($users as $user) {
            $user->notify(new ExpenseChangeRequestCreated($request));
        }

        Notification::make()
            ->title('Запрос создан')
            ->body('Уведомления отправлены всем пользователям для голосования')
            ->success()
            ->send();
    }
}
