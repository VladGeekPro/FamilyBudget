<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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

    // Методы проверки статуса
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // Методы для работы с голосованием
    public function getApprovedVotes()
    {
        return $this->votes()->where('vote', 'approved')->with('user')->get();
    }

    public function getApprovedVotesCount()
    {
        return $this->votes()->where('vote', 'approved')->count();
    }

    public function getRejectedVotes()
    {
        return $this->votes()->where('vote', 'rejected')->with('user')->get();
    }

    public function getRejectedVotesCount()
    {
        return $this->votes()->where('vote', 'rejected')->count();
    }

    public function getPendingUsers()
    {
        $votedUserIds = $this->votes()->pluck('user_id')->toArray();
        return User::whereNotIn('id', $votedUserIds)->get();
    }

    public function scopeUnanswered()
    {
        $ecr = (new static)->getTable();
        $votes = (new ExpenseChangeRequestVote)->getTable();
        $users = (new User)->getTable();

        $pendingEcrs = DB::table($ecr)
            ->where($ecr . '.status', 'pending')
            ->select($ecr . '.id as ecr_id');

        return DB::table($users)
            ->crossJoinSub($pendingEcrs, 'pe', function () {})
            ->leftJoin($votes, function ($join) use ($votes, $users) {
                $join->on($votes . '.expense_change_request_id', '=', 'pe.ecr_id')
                    ->on($votes . '.user_id', '=', $users . '.id');
            })
            ->whereNull($votes . '.id')
            ->select($users . '.email', DB::raw('COUNT(pe.ecr_id) as unanswered_count'))
            ->groupBy($users . '.email')
            ->orderBy($users . '.email');
    }

    public function hasUserVoted(User $user): bool
    {
        return $this->votes()->where('user_id', $user->id)->exists();
    }

    public function checkAndApplyIfReady(string $decision): bool
    {

        if ($this->isPending() && User::count() === $this->getApprovedVotesCount()) {
            if ($this->applyChanges()) {
                foreach (User::all() as $user) {
                    $user->notify(new \App\Notifications\ExpenseChangeRequestCompleted($this, 'completed'));
                }

                try {
                    Artisan::call('calculate:monthly-debts', ['--period' => $this->current_date?->format('m.Y') ?? $this->requested_date->format('m.Y')]);
                } catch (\Exception $e) {

                    $title = "Ошибка перерасчёта долга";
                    $message = new HtmlString(implode('<br>', [
                        '<strong>Затрата:</strong> ' . ($this->expense->id ?? 'Новая затрата'),
                        '<strong>Создано:</strong> ' . ($this->created_at?->format('d.m.Y H:i:s') ?? '-'),
                        '<strong>Пользователь:</strong> ' . ($this->user?->name ?? '-'),
                        '<strong>Ошибка:</strong> ' . (Str::limit($e->getMessage(), 500)),
                    ]));

                    foreach (User::all() as $user) {
                        $user->notify(new \App\Notifications\ErrorNotification($title, $message));
                    }
                }
            }
        } else if ($decision === 'rejected') {
            $this->update(['status' => $decision]);
            foreach (User::all() as $user) {
                $user->notify(new \App\Notifications\ExpenseChangeRequestCompleted($this, 'rejected'));
            }
        }

        return false;
    }

    public function applyChanges(): bool
    {
        try {

            $methodName = $this->action_type . 'Expense';
            if (method_exists($this, $methodName)) {
                $this->$methodName();
            } else {

                $title = "Ошибка редактирования затраты";
                $message = new HtmlString(implode('<br>', [
                    '<strong>Затрата:</strong> ' . ($this->expense->id ?? 'Новая затрата'),
                    '<strong>Создано:</strong> ' . ($this->created_at?->format('d.m.Y H:i:s') ?? '-'),
                    '<strong>Пользователь:</strong> ' . ($this->user?->name ?? '-'),
                    '<strong>Ошибка:</strong> ' . 'Не удалось отредактировать затрату, потому что небыл обнаружен следующий метод ' . $methodName,
                ]));

                foreach (User::all() as $user) {
                    $user->notify(new \App\Notifications\ErrorNotification($title, $message));
                }

                return false;
            }

            $this->update([
                'status' => 'completed',
                'applied_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {

            $title = "Ошибка редактирования затраты";
            $message = new HtmlString(implode('<br>', [
                '<strong>Затрата:</strong> ' . ($this->expense->id ?? 'Новая затрата'),
                '<strong>Создано:</strong> ' . ($this->created_at?->format('d.m.Y H:i:s') ?? '-'),
                '<strong>Пользователь:</strong> ' . ($this->user?->name ?? '-'),
                '<strong>Ошибка:</strong> ' . (Str::limit($e->getMessage(), 500)),
            ]));

            foreach (User::all() as $user) {
                $user->notify(new \App\Notifications\ErrorNotification($title, $message));
            }

            return false;
        }
    }

    protected function createExpense()
    {
        Expense::create([
            'user_id' => $this->requested_user_id,
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
        } else {

            $title = "Ошибка редактирования затраты";
            $message = new HtmlString(implode('<br>', [
                '<strong>Создано:</strong> ' . ($this->created_at?->format('d.m.Y H:i:s') ?? '-'),
                '<strong>Пользователь:</strong> ' . ($this->user?->name ?? '-'),
                '<strong>Ошибка:</strong> Не удалось отредактировать затрату, потому что она не найдена.',
            ]));

            foreach (User::all() as $user) {
                $user->notify(new \App\Notifications\ErrorNotification($title, $message));
            }
        }
    }

    protected function deleteExpense()
    {
        if ($this->expense) {
            $this->expense->delete();
        } else {

            $title = "Ошибка удаления затраты";
            $message = new HtmlString(implode('<br>', [
                '<strong>Создано:</strong> ' . ($this->created_at?->format('d.m.Y H:i:s') ?? '-'),
                '<strong>Пользователь:</strong> ' . ($this->user?->name ?? '-'),
                '<strong>Ошибка:</strong> Не удалось удалить затрату, потому что она не найдена.',
            ]));

            foreach (User::all() as $user) {
                $user->notify(new \App\Notifications\ErrorNotification($title, $message));
            }
        }
    }
}
