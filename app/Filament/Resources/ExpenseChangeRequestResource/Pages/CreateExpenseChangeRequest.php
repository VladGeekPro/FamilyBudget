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

        $foundRequest = \App\Models\ExpenseChangeRequest::where('status', 'pending')
        ->where('expense_id', $expenseId)
        ->first();

        if ($foundRequest) {

            $this->data['expense_id'] = null;

            Notification::make()
                ->warning()
                ->title('Запрос уже существует')
                ->body("Для расхода #{$expenseId} уже существует активный запрос на изменение (#{$foundRequest->id})")
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('Посмотреть запрос')
                        ->url(ExpenseChangeRequestResource::getUrl('view', ['record' => $foundRequest->id]), shouldOpenInNewTab: true)
                        ->button(),
                    \Filament\Notifications\Actions\Action::make('close')
                        ->label('Закрыть')
                        ->close(),
                ])
                ->send();

            return;
        }

        ExpenseChangeRequestResource::fillExpenseFields(
           $this->data['expense_id'],
            $this->data['action_type'],
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
