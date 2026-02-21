<?php

namespace App\Notifications;

use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\ExpenseChangeRequest;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class ExpenseChangeRequestModified extends Notification
{
    public function __construct(
        public ExpenseChangeRequest $changeRequest,
        public string $event,
        public bool $canceledVote = false
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $actionType = $this->changeRequest->action_type;

        $translationBase = "resources.notifications.warn.expense_change_request.{$this->event}";

        $body = __($translationBase . '.body', [
            'date' => $this->changeRequest->updated_at?->format('d.m.Y H:i') ?? $this->changeRequest->created_at->format('d.m.Y H:i'),
            'actionType' => __('resources.fields.action_type.notification_options.' . $actionType),
            'creator' => auth()->user()->name,
            'expense_id' => $this->changeRequest->expense_id,
            'canceledVoteText' => $this->canceledVote ? __('resources.notifications.warn.expense_change_request.edit.canceled_vote_text') : '',
        ]);

        $iconMap = [
            'create' => ['icon' => 'heroicon-o-document-plus', 'color' => 'success'],
            'edit' => ['icon' => 'heroicon-o-pencil-square', 'color' => 'warning'],
            'delete' => ['icon' => 'heroicon-o-trash', 'color' => 'danger'],
        ];

        return FilamentNotification::make()
            ->title(__($translationBase . '.title'))
            ->body(new \Illuminate\Support\HtmlString($body))
            ->icon($iconMap[$this->event]['icon'])
            ->iconColor($iconMap[$this->event]['color'])
            ->actions(array_values(array_filter([

                $this->event !== 'delete'
                    ? Action::make('view')
                    ->label(__('resources.buttons.view'))
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->url(fn() => ExpenseChangeRequestResource::getUrl('view', ['record' => $this->changeRequest->id]))
                    : null,

                Action::make('markAsRead')
                    ->label(__('resources.buttons.mark_as_read'))
                    ->icon('heroicon-o-check')
                    ->button()
                    ->color('success')
                    ->markAsRead(),
            ])))
            ->getDatabaseMessage();
    }
}
