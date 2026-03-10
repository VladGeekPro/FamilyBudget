<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaidDebts extends Model
{
    use HasFactory;
    protected $fillable = ["debt_id", "changed_debt_date", "paid_by_user_id", "payment_status", "paid_sum"];

    protected $casts = [
        'changed_debt_date' => 'datetime',
    ];

    public function debt()
    {
        return $this->belongsTo(Debt::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}
