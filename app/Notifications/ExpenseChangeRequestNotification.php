<?php

namespace App\Notifications;

use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\ExpenseChangeRequest;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;


class ExpenseChangeRequestNotification extends Notification
{

    public function __construct(
        public ExpenseChangeRequest $changeRequest,
        public User $creator
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {

        $actionType = $this->changeRequest->action_type;

        $body = __('resources.notifications.warn.expense_change_request_to_database.body', [
            'date' => $this->changeRequest->created_at->format('d.m.Y H:i'),
            'actionType' => __('resources.fields.action_type.notification_options.' . $actionType),
            'creator' => $this->creator->name,
            'expense_id' => $this->changeRequest->expense_id,
        ]);


        return FilamentNotification::make()
            ->title(__('resources.notifications.warn.expense_change_request_to_database.title'))
            ->body(new \Illuminate\Support\HtmlString($body))
            ->icon('heroicon-o-document-plus')
            ->iconColor('warning')
            ->actions([
                Action::make('view')
                    ->label(__('resources.buttons.view'))
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->url(fn() => ExpenseChangeRequestResource::getUrl('view', ['record' => $this->changeRequest->id])),

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
