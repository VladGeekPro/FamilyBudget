<?php

namespace App\Filament\Resources\ExpenseChangeRequestResource\Pages;

use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\Expense;
use App\Models\User;
use App\Notifications\ExpenseChangeRequestNotification;
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

    protected function beforeCreate(): void
    {

        if ($this->data['action_type'] === 'edit') {
            $fieldsToCheck = ['user_id', 'date', 'category_id', 'supplier_id', 'sum', 'notes'];
            $hasChanges = false;

            foreach ($fieldsToCheck as $field) {
                if ($this->data['current_' . $field] !== $this->data['requested_' . $field]) {
                    $hasChanges = true;
                    break;
                }
            }

            if (!$hasChanges) {
                Notification::make()
                    ->title(__('resources.notifications.warn.expense_change_request.title'))
                    ->body(__('resources.notifications.warn.expense_change_request.body'))
                    ->warning()
                    ->duration(10000)
                    ->send();

                $this->halt();
            }
        }
    }

    protected function afterCreate(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $user->notify(new ExpenseChangeRequestNotification($this->record, auth()->user()));
        }

    }
}
