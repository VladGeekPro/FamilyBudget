<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ExpenseChangeRequest;

class Expense extends Model
{
    protected $fillable = ["user_id", "date", "category_id", "supplier_id", "sum", "notes"];

    protected $casts = [
        'date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function changeRequest()
    {
        return $this->hasMany(ExpenseChangeRequest::class);
    }

    public function scopeCurrentMonthExpenses($query)
    {
        return $query->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->selectRaw('expenses.user_id, SUM(expenses.sum) as total_sum')
            ->groupBy('user_id')
            ->join('users', 'expenses.user_id', '=', 'users.id')
            ->addSelect('users.name as user_name', 'users.email as user_email')
            ->orderBy('user_email');
    }

    public function scopePreviousMonthsExpenses($query)
    {
        return $query->whereDate('date', '<', now()->startOfMonth())
            ->with(['supplier', 'category', 'user'])
            ->whereDoesntHave('changeRequest', function ($q) {
                $q->where('status', 'pending');
            });
    }
}
