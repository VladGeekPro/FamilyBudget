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
            $date = Carbon::createFromDate($year, $month, 1);
        } catch (\Exception $e) {
            $this->error('Неверный формат периода. Используйте mm.yyyy');
            return;
        }

        $this->info("Выполняется расчет долгов за период: {$date->translatedFormat('f Y')}");

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

        $minExpenseUser = collect($expenses)
            ->sortBy(fn($item, $key) => [$item['sum'], $key])
            ->first();

        $maxExpenseUser = collect($expenses)
            ->sortByDesc(fn($item, $key) => [$item['sum'], -$key])
            ->first();

        $difference = ($maxExpenseUser['sum'] - $minExpenseUser['sum']) / 2;

        $overpayment = Overpayment::latest('created_at')->first();

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

        $existingDebt = Debt::where('date', $date->endOfMonth())->first();

        if ($existingDebt) {
            $this->warn("Долг за {$date->translatedFormat('F Y')} уже существует для пользователя {$minExpenseUser['user']->name}❗");
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
                'date' => $date->endOfMonth(),
                'user_id' => null,
                'sum' => 0,
                'overpayment_id' => $overpayment?->id,
                'paid' => true,
                'notes' => 'Расходы были равны, никто никому не должен.',
            ]);

            $this->info('Расходы одинаковые, долг не создан');
        } else {

            // Debt::create([
            //     'date' => $date->endOfMonth(),
            //     'user_id' => $minExpenseUser['user']->id,
            //     'sum' => $difference,
            //     'overpayment_id' => $overpayment?->id,
            //     'notes' => "Создан долг для {$minExpenseUser['user']->name} на сумму {$difference} MDL",
            // ]);

            // $this->info("Создан долг для {$minExpenseUser['user']->name} на сумму {$difference} MDL");
        };
    }
}