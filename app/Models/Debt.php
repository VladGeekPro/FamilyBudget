<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Debt extends Model
{
    use HasFactory;

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
        $debtsTable = (new static)->getTable();
        $usersTable = (new User)->getTable();

        return $query
            ->select($debtsTable . '.user_id', DB::raw('COUNT(*) as unpaid_count'))
            ->where(function ($statusQuery) use ($debtsTable) {
                $statusQuery
                    ->where($debtsTable . '.payment_status', 'unpaid')
                    ->orWhere($debtsTable . '.payment_status', 'partial');
            })
            ->join($usersTable, $debtsTable . '.user_id', '=', $usersTable . '.id')
            ->addSelect($usersTable . '.email as email')
            ->groupBy($debtsTable . '.user_id')
            ->orderBy($usersTable . '.email');
    }
}
