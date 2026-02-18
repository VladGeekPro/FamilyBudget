<?php

namespace App\Notifications;

use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\ExpenseChangeRequest;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ExpenseChangeRequestCompleted extends Notification
{
    public function __construct(
        public ExpenseChangeRequest $expenseChangeRequest,
        public string $event
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $actionType = $this->expenseChangeRequest->action_type;

        $translationBase = "resources.notifications.warn.expense_change_request.{$this->event}";

        $body = __($translationBase . '.body', [
            'date' => $this->expenseChangeRequest->updated_at?->format('d.m.Y H:i') ?? $this->expenseChangeRequest->created_at->format('d.m.Y H:i'),
            'actionType' => __('resources.fields.action_type.notification_options.' . $actionType),
            'creator' => $this->expenseChangeRequest->user_id->name,
        ]);

        $iconMap = [
            'completed' => ['icon' => 'heroicon-o-check-circle', 'color' => 'success'],
            'rejected'  => ['icon' => 'heroicon-o-x-circle', 'color' => 'danger'],
        ];

        return FilamentNotification::make()
            ->title(__($translationBase . '.title'))
            ->body(new HtmlString($body))
            ->icon($iconMap[$this->event]['icon'])
            ->iconColor($iconMap[$this->event]['color'])
            ->actions([
                Action::make('view')
                    ->label(__('resources.buttons.view'))
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->url(fn() => ExpenseChangeRequestResource::getUrl('view', ['record' => $this->expenseChangeRequest->id])),

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
