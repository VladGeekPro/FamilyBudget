<?php

namespace App\Notifications;

use App\Filament\Resources\DebtResource;
use App\Models\Debt;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class DebtEditedNotification extends Notification
{
    public function __construct(
        public Debt $debt,
        public User|string $editor
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $editedByProgram = is_string($this->editor);
        $editorName = $editedByProgram ? $this->editor : $this->editor->name;
        
        $notification = $editedByProgram ? 'created_debt' : 'edited_debt';
        
        $body = __('resources.notifications.warn.' . $notification . '.body', [
            'user' => $editorName,
            'date' => $this->debt->date->format('d.m.Y'),
            'sum' => number_format($this->debt->debt_sum, 2, ',', ' '),
            'notes' => $this->debt->notes,
        ]);

        return FilamentNotification::make()
            ->title(__('resources.notifications.warn.' . $notification . '.title'))
            ->body(new \Illuminate\Support\HtmlString($body))
            ->icon('heroicon-o-pencil-square')
            ->iconColor('info')
            ->actions([
                Action::make('view')
                    ->label(__('resources.buttons.view'))
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->url(fn() => DebtResource::getUrl('index') . '?tableAction=view&tableActionRecord=' . $this->debt->id),

                Action::make('markAsRead')
                    ->label(__('resources.buttons.mark_as_read'))
                    ->icon('heroicon-o-check')
                    ->button()
                    ->color('success')
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
