<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'user_id',
        'action_type',
        'requested_date',
        'requested_user_id',
        'requested_category_id',
        'requested_supplier_id',
        'requested_sum',
        'requested_notes',
        'current_date',
        'current_user_id',
        'current_category_id',
        'current_supplier_id',
        'current_sum',
        'current_notes',
        'notes',
        'status',
        'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'requested_date' => 'date',
        'requested_sum' => 'decimal:2',
        'current_date' => 'date',
        'current_sum' => 'decimal:2',
    ];

    // Связи
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_user_id');
    }

    public function requestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'requested_category_id');
    }

    public function requestedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'requested_supplier_id');
    }

    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    public function currentCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'current_category_id');
    }

    public function currentSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'current_supplier_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ExpenseChangeRequestVote::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Методы проверки статуса
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    // Методы для работы с голосованием
    public function getAllVotes()
    {
        return $this->votes()->with('user')->get();
    }

    public function getApprovedVotes()
    {
        return $this->votes()->where('vote', 'approved')->with('user')->get();
    }

    public function getRejectedVotes()
    {
        return $this->votes()->where('vote', 'rejected')->with('user')->get();
    }

    public function getPendingUsers()
    {
        $votedUserIds = $this->votes()->pluck('user_id')->toArray();
        return User::whereNotIn('id', $votedUserIds)->get();
    }

    public function hasUserVoted(User $user): bool
    {
        return $this->votes()->where('user_id', $user->id)->exists();
    }

    public function getUserVote(User $user)
    {
        return $this->votes()->where('user_id', $user->id)->first();
    }

    // Проверка готовности для применения
    public function canBeApplied(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $totalUsers = User::count();
        $approvedVotes = $this->votes()->where('vote', 'approved')->count();
        $rejectedVotes = $this->votes()->where('vote', 'rejected')->count();

        // Если есть хоть один отклонивший голос
        if ($rejectedVotes > 0) {
            return false;
        }

        // Если все пользователи одобрили
        return $approvedVotes === $totalUsers;
    }

    // Автоматическая проверка и применение после голосования
    public function checkAndApplyIfReady(): bool
    {
        if ($this->canBeApplied()) {
            $result = $this->applyChanges();
            
            if ($result) {
                // Уведомляем всех о завершении
                $users = User::all();
                foreach ($users as $user) {
                    $user->notify(new \App\Notifications\ExpenseChangeRequestCompleted($this, true));
                }
            }
            
            return $result;
        }
        
        // Проверяем, есть ли отклоняющие голоса
        $rejectedVotes = $this->votes()->where('vote', 'rejected')->count();
        if ($rejectedVotes > 0 && $this->isPending()) {
            $this->update(['status' => 'rejected']);
            
            // Уведомляем всех об отклонении
            $users = User::all();
            foreach ($users as $user) {
                $user->notify(new \App\Notifications\ExpenseChangeRequestCompleted($this, false));
            }
        }
        
        return false;
    }

    // Применение изменений
    public function applyChanges(): bool
    {
        if (!$this->canBeApplied()) {
            return false;
        }

        try {
            switch ($this->action_type) {
                case 'create':
                    $this->createExpense();
                    break;
                case 'edit':
                    $this->editExpense();
                    break;
                case 'delete':
                    $this->deleteExpense();
                    break;
            }

            $this->update([
                'status' => 'completed',
                'applied_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to apply expense change request: ' . $e->getMessage());
            return false;
        }
    }

    protected function createExpense()
    {
        Expense::create([
            'user_id' => $this->requested_user_id ?? $this->user_id,
            'date' => $this->requested_date,
            'category_id' => $this->requested_category_id,
            'supplier_id' => $this->requested_supplier_id,
            'sum' => $this->requested_sum,
            'notes' => $this->requested_notes,
        ]);
    }

    protected function editExpense()
    {
        if ($this->expense) {
            $updateData = [];
            
            if ($this->requested_user_id) $updateData['user_id'] = $this->requested_user_id;
            if ($this->requested_date) $updateData['date'] = $this->requested_date;
            if ($this->requested_category_id) $updateData['category_id'] = $this->requested_category_id;
            if ($this->requested_supplier_id) $updateData['supplier_id'] = $this->requested_supplier_id;
            if ($this->requested_sum !== null) $updateData['sum'] = $this->requested_sum;
            if ($this->requested_notes !== null) $updateData['notes'] = $this->requested_notes;
            
            $this->expense->update($updateData);
        }
    }

    protected function deleteExpense()
    {
        if ($this->expense) {
            $this->expense->delete();
        }
    }

    // Методы для удобства работы с заявками
    public function getRequestedData(): array
    {
        return [
            'user_id' => $this->requested_user_id,
            'date' => $this->requested_date,
            'category_id' => $this->requested_category_id,
            'supplier_id' => $this->requested_supplier_id,
            'sum' => $this->requested_sum,
            'notes' => $this->requested_notes,
        ];
    }

    public function setRequestedDataFromExpense(Expense $expense): void
    {
        $this->requested_user_id = $expense->user_id;
        $this->requested_date = $expense->date;
        $this->requested_category_id = $expense->category_id;
        $this->requested_supplier_id = $expense->supplier_id;
        $this->requested_sum = $expense->sum;
        $this->requested_notes = $expense->notes;
    }

    public function hasRequestedChanges(): bool
    {
        return $this->requested_user_id !== null ||
               $this->requested_date !== null ||
               $this->requested_category_id !== null ||
               $this->requested_supplier_id !== null ||
               $this->requested_sum !== null ||
               $this->requested_notes !== null;
    }

    public function getChangeSummary(): array
    {
        if (!$this->expense || $this->action_type === 'create') {
            return ['action' => $this->action_type, 'changes' => $this->getRequestedData()];
        }

        $changes = [];
        
        if ($this->requested_user_id && $this->requested_user_id != $this->expense->user_id) {
            $changes['user'] = [
                'old' => $this->expense->user->name ?? 'Unknown',
                'new' => $this->requestedUser->name ?? 'Unknown'
            ];
        }
        
        if ($this->requested_date && $this->requested_date != $this->expense->date) {
            $changes['date'] = ['old' => $this->expense->date, 'new' => $this->requested_date];
        }
        
        if ($this->requested_category_id && $this->requested_category_id != $this->expense->category_id) {
            $changes['category'] = [
                'old' => $this->expense->category->name ?? 'Unknown',
                'new' => $this->requestedCategory->name ?? 'Unknown'
            ];
        }
        
        if ($this->requested_supplier_id && $this->requested_supplier_id != $this->expense->supplier_id) {
            $changes['supplier'] = [
                'old' => $this->expense->supplier->name ?? 'Unknown',
                'new' => $this->requestedSupplier->name ?? 'Unknown'
            ];
        }
        
        if ($this->requested_sum !== null && $this->requested_sum != $this->expense->sum) {
            $changes['sum'] = ['old' => $this->expense->sum, 'new' => $this->requested_sum];
        }
        
        if ($this->requested_notes !== null && $this->requested_notes != $this->expense->notes) {
            $changes['notes'] = ['old' => $this->expense->notes, 'new' => $this->requested_notes];
        }

        return ['action' => $this->action_type, 'changes' => $changes];
    }
}