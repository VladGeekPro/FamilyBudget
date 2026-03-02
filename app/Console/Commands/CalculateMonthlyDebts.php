<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Models\Expense;
use App\Models\Overpayment;
use App\Models\PaidDebt;
use App\Models\PaidDebts;
use App\Models\User;
use App\Notifications\DebtEditedNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateMonthlyDebts extends Command
{

    protected $signature = 'calculate:monthly-debts {--period= : Период в формате mm.yyyy }';

    protected $description = 'Рассчитать и создать долги на основе расходов пользователей за месяц';

    public function handle()
    {
        $period = $this->option('period') ?: now()->subMonth()->format('m.Y');

        try {
            [$month, $year] = explode('.', $period);
            $date = Carbon::create($year, $month)->endOfMonth();
        } catch (\Exception $e) {
            $this->error('Неверный формат периода. Используйте mm.yyyy');
            return;
        }

        $this->info("Выполняется расчет долгов за период: " . $date->translatedFormat('F Y'));

        $users = User::all();

        if ($users->count() < 2) {
            $this->error('Недостаточно пользователей для расчета долгов');
            return;
        }

        $expenses = [];
        foreach ($users as $user) {
            $sum = Expense::where('user_id', $user->id)
                ->whereMonth('date', $date->month)
                ->whereYear('date', $date->year)
                ->sum('sum');

            $expenses[$user->id] = [
                'user' => $user,
                'sum' => $sum
            ];

            $this->line("{$user->name}: {$sum} MDL");
        }

        $collection = collect($expenses)
            ->sortBy(fn($item, $key) => [$item['sum'], $key]);

        $minExpenseUser = $collection->first();
        $maxExpenseUser = $collection->last();

        $difference = ($maxExpenseUser['sum'] - $minExpenseUser['sum']) / 2;

        $overpayment = Overpayment::where('created_at', '<=', $date)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($overpayment) {
            if ($minExpenseUser['user']->id === $overpayment->user_id) {
                $difference += $overpayment->sum;
            } else {
                $difference = $difference - $overpayment->sum;
                if ($difference < 0) {
                    [$minExpenseUser, $maxExpenseUser] = [$maxExpenseUser, $minExpenseUser];
                    $difference = abs($difference);
                }
            }
        }

        $existingDebt = Debt::where('date', $date)->first();

        if ($existingDebt) {
            $this->warn("Долг за {$date->translatedFormat('d F Y')} уже существует для пользователя {$minExpenseUser['user']->name}❗");
            if (in_array($existingDebt->payment_status, ['partial', 'paid']) && $existingDebt->user_id) {
                $paidSum = $existingDebt->payment_status === 'partial' ? $existingDebt->partial_sum : $existingDebt->debt_sum;
                PaidDebts::create([
                    'debt_id' => $existingDebt->id,
                    'changed_debt_date' => now(),
                    'paid_by_user_id' => $existingDebt->user_id,
                    'payment_status' => $existingDebt->payment_status,
                    'paid_sum' => $paidSum,
                ]);
            }

            $debtTable = (new Debt)->getTable();
            $paidDebtTable = (new PaidDebts)->getTable();

            $paidDebtsRecords = Debt::where($debtTable . '.id', $existingDebt->id)
                ->join($paidDebtTable, $debtTable . '.id', '=', $paidDebtTable . '.debt_id')
                ->select(
                    $paidDebtTable . '.paid_by_user_id as user_id',
                    DB::raw('SUM(' . $paidDebtTable . '.paid_sum) as paid_sum')
                )
                ->groupBy($paidDebtTable . '.paid_by_user_id')
                ->get();

            foreach ($paidDebtsRecords as $paidDebt) {
                if ($maxExpenseUser['user']->id === $paidDebt['user_id']) {
                    $difference += $paidDebt['paid_sum'];
                } elseif ($minExpenseUser['user']->id === $paidDebt['user_id']) {
                    $difference -= $paidDebt['paid_sum'];
                    if ($difference < 0) {
                        [$minExpenseUser, $maxExpenseUser] = [$maxExpenseUser, $minExpenseUser];
                        $difference = abs($difference);
                    }
                }
            }
        }

        if ($difference == 0) {
            $debtMessage = 'Расходы одинаковые, никто никому не должен.';
            $attributes = [
                'date' => $date,
                'user_id' => null,
                'overpayment_id' => $overpayment?->id,
                'payment_status' => 'paid',
                'date_paid' => now(),
                'partial_sum' => 0,
                'notes' => $debtMessage,
            ];
        } else {
            $formattedDifference = number_format($difference, 2, '.', ' ');
            $debtMessage = __('resources.fields.notes_message.unpaid', [
                'debtor' => $minExpenseUser['user']->name,
                'creditor' => $maxExpenseUser['user']->name,
                'sum' => $formattedDifference,
            ]);

            $attributes = [
                'date' => $date,
                'user_id' => $minExpenseUser['user']->id,
                'overpayment_id' => $overpayment?->id,
                'payment_status' => 'unpaid',
                'date_paid' => null,
                'partial_sum' => 0,
                'debt_sum' => $difference,
                'notes' => $debtMessage,
            ];
        }

        if ($existingDebt) {
            $existingDebt->update($attributes);
            $debt = $existingDebt;
            $event = 'edited_debt';
        } else {
            $debt = Debt::create($attributes);
            $event = 'created_debt';
        }

        $this->info($debtMessage);

        $editor = 'Программа расчёта долгов';

        $users = User::all();
        foreach ($users as $user) {
            $user->notify(new DebtEditedNotification($debt, $editor, $event));
        }
    }
}
