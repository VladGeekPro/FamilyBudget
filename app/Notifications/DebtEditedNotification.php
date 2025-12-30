<?php

namespace App\Notifications;

use App\Models\Debt;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class DebtEditedNotification extends Notification
{
    public function __construct(
        public Debt $debt,
        public User $editor
    ) {}

    public function via($notifiable): array
    {
        return ['database']; // можно добавить 'mail', 'broadcast'
    }

    public function toDatabase($notifiable): array
    {
        return FilamentNotification::make()
            ->title('Задолженность отредактирована')
            ->body("Пользователь {$this->editor->name} отредактировал задолженность на сумму " . number_format($this->debt->debt_sum, 2, ',', ' ') . " MDL")
            ->icon('heroicon-o-pencil-square')
            ->iconColor('warning')
            ->getDatabaseMessage();
    }

    // Если нужно добавить email:
    // public function toMail($notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->subject('Задолженность отредактирована')
    //         ->line("Пользователь {$this->editor->name} отредактировал задолженность")
    //         ->action('Посмотреть', url('/admin/debts'));
    // }
}
