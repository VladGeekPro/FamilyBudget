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

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('vote', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('vote', 'rejected');
    }

    public function scopeForRequest($query, $requestId)
    {
        return $query->where('expense_change_request_id', $requestId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Методы проверки
    public function isApproved(): bool
    {
        return $this->vote === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->vote === 'rejected';
    }

    public static function vote(int $requestId, int $userId, string $approved, ?string $notes = null): self
    {

        return self::updateOrCreate(
            [
                'expense_change_request_id' => $requestId,
                'user_id' => $userId
            ],
            [
                'vote' => $approved,
                'notes' => $notes,
            ]
        );
    }

    protected static function booted(): void
    {
        static::saved(function ($vote) {

            foreach (User::all() as $user) {
                $user->notify(
                    new \App\Notifications\ExpenseChangeRequestVoted($vote)
                );
            }

            $vote->expenseChangeRequest->checkAndApplyIfReady($vote->vote);
        });
    }
}
