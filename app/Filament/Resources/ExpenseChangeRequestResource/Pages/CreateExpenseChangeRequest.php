<?php

namespace App\Filament\Resources\ExpenseChangeRequestResource\Pages;

use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\Expense;
use App\Models\User;
use App\Notifications\ExpenseChangeRequestModified;
use Filament\Actions;
use App\Filament\Resources\Base\CreateBase;
use App\Models\ExpenseChangeRequestVote;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

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
                ->title(__('resources.notifications.warn.expense_change_request.already_exists.title'))
                ->body(__('resources.notifications.warn.expense_change_request.already_exists.body', ['expenseId' => $expenseId, 'foundRequestId' => $foundRequest->id]))
                ->persistent()
                ->actions([
                    Action::make('view')
                        ->label(__('resources.buttons.view'))
                        ->button()
                        ->icon('heroicon-o-eye')
                        ->url(ExpenseChangeRequestResource::getUrl('view', ['record' => $foundRequest->id]), shouldOpenInNewTab: true),
                ])
                ->send();

            return;
        } else {
            $this->data['expense_id'] = $expenseId;
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
                    ->title(__('resources.notifications.warn.expense_change_request.no_changes.title'))
                    ->body(__('resources.notifications.warn.expense_change_request.no_changes.body'))
                    ->warning()
                    ->duration(10000)
                    ->send();

                $this->halt();
            }
        }
    }

    protected function afterCreate(): void
    {
        ExpenseChangeRequestVote::vote(
            $this->record->id,
            $this->record->user_id,
            'approved'
        );

        $users = User::all();

        foreach ($users as $user) {
            $user->notify(new ExpenseChangeRequestModified($this->record, 'create'));
        }
    }
}
