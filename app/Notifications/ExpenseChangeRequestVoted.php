<?php

namespace App\Notifications;

use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\ExpenseChangeRequestVote;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ExpenseChangeRequestVoted extends Notification implements ShouldQueue
{
    public function __construct(
        public ExpenseChangeRequestVote $vote
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $isApproved = $this->vote->vote === 'approved';

        $voteText = $isApproved ? 'одобрил' : 'отклонил';
        $icon = $isApproved ? 'heroicon-o-hand-thumb-up' : 'heroicon-o-hand-thumb-down';
        $iconColor = $isApproved ? 'success' : 'danger';

        $expenseChangeRequestId = $this->vote->expenseChangeRequest->id;

        $body = "{$this->vote->user->name} {$voteText} запрос #{$expenseChangeRequestId}";
        if (!empty($this->vote->notes)) {
            $body .= "<br><br>💬 {$this->vote->notes}";
        }

        return FilamentNotification::make()
            ->title('Новый голос по запросу')
            ->body(new HtmlString($body))
            ->icon($icon)
            ->iconColor($iconColor)
            ->actions([
                Action::make('view')
                    ->label(__('resources.buttons.view'))
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->url(fn() => ExpenseChangeRequestResource::getUrl('view', ['record' => $expenseChangeRequestId])),
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
