<?php

namespace App\Notifications;

use App\Models\ExpenseChangeRequest;
use App\Models\ExpenseChangeRequestVote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Actions\Action;

class ExpenseChangeRequestVoted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ExpenseChangeRequest $expenseChangeRequest,
        public ExpenseChangeRequestVote $vote
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'expense_change_request_voted',
            'expense_change_request_id' => $this->expenseChangeRequest->id,
            'voter_name' => $this->vote->user->name,
            'vote' => $this->vote->vote,
            'notes' => $this->vote->notes,
        ];
    }

    public function toFilament(object $notifiable): FilamentNotification
    {
        $voteText = $this->vote->vote === 'approved' ? 'одобрил' : 'отклонил';
        $color = $this->vote->vote === 'approved' ? 'success' : 'danger';
        $icon = $this->vote->vote === 'approved' ? 'heroicon-o-hand-thumb-up' : 'heroicon-o-hand-thumb-down';

        return FilamentNotification::make()
            ->title('Новый голос получен')
            ->body("{$this->vote->user->name} {$voteText} запрос #{$this->expenseChangeRequest->id}")
            ->icon($icon)
            ->color($color)
            ->actions([
                Action::make('view_votes')
                    ->label('Все голоса')
                    ->url('/admin/expense-change-requests/' . $this->expenseChangeRequest->id)
                    ->button(),
            ])
            ->persistent();
    }
}