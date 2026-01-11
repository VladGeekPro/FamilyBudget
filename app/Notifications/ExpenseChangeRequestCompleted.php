<?php

namespace App\Notifications;

use App\Models\ExpenseChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Actions\Action;

class ExpenseChangeRequestCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ExpenseChangeRequest $expenseChangeRequest,
        public bool $approved = true
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $actionText = match ($this->expenseChangeRequest->action_type) {
            'create' => 'создание нового расхода',
            'edit' => 'изменение расхода',
            'delete' => 'удаление расхода',
        };

        if ($this->approved) {
            return (new MailMessage)
                ->subject('Запрос на изменение одобрен и выполнен')
                ->line("Ваш запрос на {$actionText} был одобрен всеми пользователями и успешно выполнен.")
                ->when($this->expenseChangeRequest->expense, function ($mail) {
                    return $mail->line("Расход: {$this->expenseChangeRequest->expense->supplier->name} - {$this->expenseChangeRequest->expense->sum} MDL");
                })
                ->line("Дата выполнения: {$this->expenseChangeRequest->applied_at->format('d.m.Y H:i')}")
                ->action('Посмотреть детали', url('/admin/expense-change-requests/' . $this->expenseChangeRequest->id))
                ->line('Спасибо за использование нашей системы!');
        } else {
            return (new MailMessage)
                ->subject('Запрос на изменение отклонён')
                ->line("Ваш запрос на {$actionText} был отклонён.")
                ->when($this->expenseChangeRequest->expense, function ($mail) {
                    return $mail->line("Расход: {$this->expenseChangeRequest->expense->supplier->name} - {$this->expenseChangeRequest->expense->sum} MDL");
                })
                ->line("Причина вашего запроса: {$this->expenseChangeRequest->notes}")
                ->action('Посмотреть голоса', url('/admin/expense-change-requests/' . $this->expenseChangeRequest->id))
                ->line('Вы можете создать новый запрос с учётом комментариев.');
        }
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->approved ? 'expense_change_request_completed' : 'expense_change_request_rejected',
            'expense_change_request_id' => $this->expenseChangeRequest->id,
            'action_type' => $this->expenseChangeRequest->action_type,
            'approved' => $this->approved,
            'applied_at' => $this->expenseChangeRequest->applied_at?->toISOString(),
        ];
    }

    public function toFilament(object $notifiable): FilamentNotification
    {
        if ($this->approved) {
            $actionText = match ($this->expenseChangeRequest->action_type) {
                'create' => 'Новый расход создан',
                'edit' => 'Расход изменён',
                'delete' => 'Расход удалён',
            };

            return FilamentNotification::make()
                ->title('Запрос выполнен')
                ->body($actionText . ' - запрос #' . $this->expenseChangeRequest->id)
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->actions([
                    Action::make('view')
                        ->label('Подробнее')
                        ->url('/admin/expense-change-requests/' . $this->expenseChangeRequest->id)
                        ->button(),
                ])
                ->persistent();
        } else {
            return FilamentNotification::make()
                ->title('Запрос отклонён')
                ->body('Запрос #' . $this->expenseChangeRequest->id . ' был отклонён')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->actions([
                    Action::make('view_votes')
                        ->label('Посмотреть голоса')
                        ->url('/admin/expense-change-requests/' . $this->expenseChangeRequest->id)
                        ->button(),
                    Action::make('create_new')
                        ->label('Новый запрос')
                        ->url('/admin/expense-change-requests/create')
                        ->link(),
                ])
                ->persistent();
        }
    }
}