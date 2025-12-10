<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'debts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'user_id',
        'sum',
        'paid',
        'notes',
        'date_paid',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'datetime',
        'date_paid' => 'datetime',
        'paid' => 'boolean',
        'sum' => 'decimal:2',
    ];

    /**
     * Get the user that owns the debt.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include unpaid debts.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('paid', false);
    }

    /**
     * Scope a query to only include paid debts.
     */
    public function scopePaid($query)
    {
        return $query->where('paid', true);
    }

    /**
     * Scope a query to only include debts for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Mark the debt as paid.
     */
    public function markAsPaid()
    {
        $this->update([
            'paid' => true,
            'date_paid' => now(),
        ]);
    }

    /**
     * Mark the debt as unpaid.
     */
    public function markAsUnpaid()
    {
        $this->update([
            'paid' => false,
            'date_paid' => null,
        ]);
    }

    /**
     * Get the outstanding amount for unpaid debts.
     */
    public function getOutstandingAmount()
    {
        return $this->where('paid', false)->sum('sum');
    }
}
