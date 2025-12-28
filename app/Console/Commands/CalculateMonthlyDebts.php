<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Models\Expense;
use App\Models\Overpayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
                    $minExpenseUser = $maxExpenseUser;
                    $difference = abs($difference);
                }
            }
        }

        $previousDebts = [];
        foreach ($users as $user) {
            $debts = Debt::where('user_id', $user->id)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->where('date', '<', $date)
                ->get();

            $totalPreviousDebt = $debts->sum(function ($debt) {
                if ($debt->payment_status === 'unpaid') {
                    return $debt->debt_sum;
                }
                return $debt->debt_sum - $debt->partial_sum;
            });

            $previousDebts[$user->id] = [
                'user' => $user,
                'sum' => $totalPreviousDebt
            ];

            if ($totalPreviousDebt > 0) {
                $this->line("Предыдущий долг {$user->name}: {$totalPreviousDebt} MDL");
            }
        }

        // Написать код, который учитывает предыдущие долги при расчете текущего долга

        $existingDebt = Debt::where('date', $date)->first();

        if ($existingDebt) {
            $this->warn("Долг за {$date->translatedFormat('d F Y')} уже существует для пользователя {$minExpenseUser['user']->name}❗");
            if ($this->option('period')) {
                if (!$this->confirm('Вы хотите пересчитать долг?', false)) {
                    $this->info('Операция отменена');
                    return;
                }

                $existingDebt->delete();
            } else {
                $existingDebt->delete();
            }
        }

        if ($difference == 0) {

            Debt::create([
                'date' => $date,
                'user_id' => null,
                'overpayment_id' => $overpayment?->id,
                'payment_status' => 'paid',
                'date_paid' => now(),
                'notes' => 'Расходы были равны, никто никому не должен.',
            ]);

            $this->info('Расходы одинаковые, долг не создан');
        } else {

            Debt::create([
                'date' => $date,
                'user_id' => $minExpenseUser['user']->id,
                'overpayment_id' => $overpayment?->id,
                'debt_sum' => $difference,
                'notes' => "{$minExpenseUser['user']->name} должен заплатить {$maxExpenseUser['user']->name} {$difference} MDL.",
            ]);

            $this->info("{$minExpenseUser['user']->name} должен заплатить {$maxExpenseUser['user']->name} {$difference} MDL.");
        };
    }
}
