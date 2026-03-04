<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ErrorNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $errorMessage,
    ) {
        $this->id = (string) Str::ulid();
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title)
            ->body($this->errorMessage)
            ->icon('heroicon-o-exclamation-circle')
            ->iconColor('danger')
            ->actions([
                Action::make('markAsRead')
                    ->label(__('resources.buttons.mark_as_read'))
                    ->icon('heroicon-o-check')
                    ->button()
                    ->color('success')
                    ->markAsRead()
                    ->extraAttributes(['class' => 'ml-auto']),
            ])
            ->getDatabaseMessage();
    }
}
