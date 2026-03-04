<?php

namespace App\Notifications;

use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\ExpenseChangeRequestVote;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ExpenseChangeRequestVoted extends Notification
{
    public function __construct(
        public ExpenseChangeRequestVote $vote
    ) {
        $this->id = (string) Str::ulid();
    }

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


        $body = "{$this->vote->user->name} {$voteText}(а) запрос на редактирование затраты #{$this->vote->expenseChangeRequest->expense_id}";
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
                    ->url(fn() => ExpenseChangeRequestResource::getUrl('view', ['record' => $this->vote->expenseChangeRequest->id])),
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
