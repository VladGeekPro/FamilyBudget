<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Models\Expense;
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
            // $date = \Carbon\Carbon::createFromDate((int)$year, (int)$month, 1);
        } catch (\Exception $e) {
            $this->error('Неверный формат периода. Используйте mm.yyyy');
            return;
        }

        // [$month, $year] = explode('.', $period);        
        // $targetDate = Carbon::createFromFormat('Y-m', $month . '-' . $year);

        // $this->info("Расчет долгов за {$targetDate->format('F Y')}");

        // // Получить всех пользователей (предполагаем семейный бюджет с 2 пользователями)
        // $users = User::all();

        // if ($users->count() < 2) {
        //     $this->error('Недостаточно пользователей для расчета долгов');
        //     return;
        // }

        // // Рассчитать суммы расходов каждого пользователя за месяц
        // $expenses = [];
        // foreach ($users as $user) {
        //     $sum = Expense::where('user_id', $user->id)
        //         ->whereMonth('date', $targetDate->month)
        //         ->whereYear('date', $targetDate->year)
        //         ->sum('sum');

        //     $expenses[$user->id] = [
        //         'user' => $user,
        //         'sum' => $sum
        //     ];

        //     $this->line("{$user->name}: {$sum} MDL");
        // }

        // // Найти пользователя с максимальными расходами
        // $maxExpenseUser = collect($expenses)->sortByDesc('sum')->first();
        // $minExpenseUser = collect($expenses)->sortBy('sum')->first();

        // $difference = $maxExpenseUser['sum'] - $minExpenseUser['sum'];

        // if ($difference <= 0) {
        //     $this->info('Расходы равны, долги не созданы');
        //     return;
        // }

        // // Проверить, существует ли уже долг за этот месяц для этого пользователя
        // $existingDebt = Debt::where('user_id', $minExpenseUser['user']->id)
        //     ->whereMonth('date', $targetDate->month)
        //     ->whereYear('date', $targetDate->year)
        //     ->where('notes', 'like', "%{$targetDate->format('F Y')}%")
        //     ->first();

        // if ($existingDebt) {
        //     $this->warn("Долг за {$targetDate->format('F Y')} уже существует");
        //     return;
        // }

        // // Создать долг
        // Debt::create([
        //     'user_id' => $minExpenseUser['user']->id,
        //     'sum' => $difference,
        //     'date' => $targetDate->endOfMonth(),
        //     'notes' => "Долг за {$targetDate->format('F Y')} (расходы {$maxExpenseUser['user']->name}: {$maxExpenseUser['sum']} MDL, {$minExpenseUser['user']->name}: {$minExpenseUser['sum']} MDL)",
        //     'paid' => false,
        // ]);

        // $this->info("Создан долг для {$minExpenseUser['user']->name} на сумму {$difference} MDL");
    }
}
