<?php

namespace App\Notifications;

use App\Models\ExpenseChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Actions\Action;

class ExpenseChangeRequestCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ExpenseChangeRequest $expenseChangeRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $actionText = match ($this->expenseChangeRequest->action_type) {
            'create' => 'создать новый расход',
            'edit' => 'изменить расход',
            'delete' => 'удалить расход',
        };

        return (new MailMessage)
            ->subject('Новый запрос на изменение расхода')
            ->line("Пользователь {$this->expenseChangeRequest->user->name} запросил разрешение {$actionText}.")
            ->when($this->expenseChangeRequest->expense, function ($mail) {
                return $mail->line("Расход: {$this->expenseChangeRequest->expense->supplier->name} - {$this->expenseChangeRequest->expense->sum} MDL");
            })
            ->line("Причина: {$this->expenseChangeRequest->notes}")
            ->action('Перейти к голосованию', url('/admin/expense-change-requests/' . $this->expenseChangeRequest->id))
            ->line('Ваш голос важен для принятия решения.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'expense_change_request_created',
            'expense_change_request_id' => $this->expenseChangeRequest->id,
            'user_name' => $this->expenseChangeRequest->user->name,
            'action_type' => $this->expenseChangeRequest->action_type,
            'notes' => $this->expenseChangeRequest->notes,
            'expense_id' => $this->expenseChangeRequest->expense_id,
        ];
    }

    public function toFilament(object $notifiable): FilamentNotification
    {
        $actionText = match ($this->expenseChangeRequest->action_type) {
            'create' => 'создать новый расход',
            'edit' => 'изменить расход',
            'delete' => 'удалить расход',
        };

        return FilamentNotification::make()
            ->title('Новый запрос на изменение')
            ->body("{$this->expenseChangeRequest->user->name} запросил разрешение {$actionText}")
            ->icon('heroicon-o-document-text')
            ->color('info')
            ->actions([
                Action::make('vote')
                    ->label('Голосовать')
                    ->url('/admin/expense-change-requests/' . $this->expenseChangeRequest->id)
                    ->button(),
                Action::make('view')
                    ->label('Подробнее')
                    ->url('/admin/expense-change-requests/' . $this->expenseChangeRequest->id)
                    ->link(),
            ])
            ->persistent();
    }
}