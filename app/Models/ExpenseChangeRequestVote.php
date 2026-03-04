<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseChangeRequestVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_change_request_id',
        'user_id',
        'vote',
        'notes',
    ];

    // Связи
    public function expenseChangeRequest(): BelongsTo
    {
        return $this->belongsTo(ExpenseChangeRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function vote(int $requestId, int $userId, string $vote, ?string $notes = null): self
    {

        return self::updateOrCreate(
            [
                'expense_change_request_id' => $requestId,
                'user_id' => $userId
            ],
            [
                'vote' => $vote,
                'notes' => $notes,
            ]
        );
    }

    protected static function booted(): void
    {
        static::saved(function ($vote) {

            $users = User::query()
                ->whereKeyNot(auth()->id())
                ->get();


            foreach ($users as $user) {
                $user->notify(
                    new \App\Notifications\ExpenseChangeRequestVoted($vote)
                );
            }

            $vote->expenseChangeRequest->checkAndApplyIfReady($vote->vote);
        });
    }
}
