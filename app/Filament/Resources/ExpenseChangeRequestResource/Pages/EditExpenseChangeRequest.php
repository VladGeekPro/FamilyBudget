<?php

namespace App\Filament\Resources\ExpenseChangeRequestResource\Pages;

use App\Filament\Resources\Base\EditBase;
use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\ExpenseChangeRequestVote;
use App\Models\User;
use App\Notifications\ExpenseChangeRequestModified;
use Filament\Actions;

class EditExpenseChangeRequest extends EditBase
{
    protected static string $resource = ExpenseChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->status === 'pending')
                ->successNotificationTitle(fn() => $this->getDeletedNotificationTitle())
                ->after(function () {
                    $users = User::all();
                    foreach ($users as $user) {
                        $user->notify(new ExpenseChangeRequestModified($this->record, 'delete'));
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {

        $canceledVotes = [];
        foreach ($this->record->votes as $vote) {
            if ($vote->user_id !== auth()->id()) {
                $canceledVotes[] = $vote->user_id;
                ExpenseChangeRequestVote::destroy($vote->id);
                $vote->user->notify(new ExpenseChangeRequestModified($this->record, 'edit', true));
            }
        }

        $users = User::whereNotIn('id', $canceledVotes)->get();
        foreach ($users as $user) {
            $user->notify(new ExpenseChangeRequestModified($this->record, 'edit'));
        }

        sleep(1);
        ExpenseChangeRequestVote::vote(
            $this->record->id,
            auth()->id(),
            'approved'
        );
    }
}
