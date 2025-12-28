<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{

    protected $table = 'debts';

    protected $fillable = [
        'date',
        'user_id',
        'debt_sum',
        'overpayment_id',
        'date_paid',
        'payment_status',
        'partial_sum',
        'notes',
    ];

    protected $casts = [
        'date' => 'datetime',
        'date_paid' => 'datetime',
        'payment_status' => 'string',
        'debt_sum' => 'decimal:2',
        'partial_sum' => 'decimal:2',
    ];

    /**
     * Get the user that owns the debt.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function overpayment()
    {
        return $this->belongsTo(Overpayment::class);
    }

    public function scopeUnpaid($query)
    {
        return $query
            ->selectRaw('debts.user_id, COUNT(*) as unpaid_count')
            ->where('payment_status', 'unpaid')
            ->groupBy('user_id')
            ->join('users', 'debts.user_id', '=', 'users.id')
            ->addSelect('users.name as user_name', 'users.email as user_email')
            ->orderBy('user_email');
    }

    

    // /**
    //  * Scope a query to only include unpaid debts.
    //  */
    // public function scopeUnpaid($query)
    // {
    //     return $query->where('paid', false);
    // }

    // /**
    //  * Scope a query to only include paid debts.
    //  */
    // public function scopePaid($query)
    // {
    //     return $query->where('paid', true);
    // }

    // /**
    //  * Scope a query to only include debts for a specific user.
    //  */
    // public function scopeForUser($query, $userId)
    // {
    //     return $query->where('user_id', $userId);
    // }

    // /**
    //  * Mark the debt as paid.
    //  */
    // public function markAsPaid()
    // {
    //     $this->update([
    //         'paid' => true,
    //         'date_paid' => now(),
    //     ]);
    // }

    // /**
    //  * Mark the debt as unpaid.
    //  */
    // public function markAsUnpaid()
    // {
    //     $this->update([
    //         'paid' => false,
    //         'date_paid' => null,
    //     ]);
    // }

    // /**
    //  * Get the outstanding amount for unpaid debts.
    //  */
    // public function getOutstandingAmount()
    // {
    //     return $this->where('paid', false)->sum('sum');
    // }
}
