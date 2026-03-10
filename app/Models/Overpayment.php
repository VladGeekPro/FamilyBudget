<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overpayment extends Model
{
    use HasFactory;
    protected $table = 'overpayments';

    protected $fillable = ['user_id', 'sum', 'notes'];

    protected $casts = [
        'sum' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
