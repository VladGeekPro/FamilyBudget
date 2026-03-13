<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Models\PaidDebt;
use App\Models\PaidDebts;
use App\Models\User;
use App\Notifications\DebtEditedNotification;
use App\Services\DebtCalculationService;
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

        $result = DebtCalculationService::calculate($date->copy()->startOfMonth(), $date);

        if (! $result['hasEnoughUsers']) {
            $this->error('Недостаточно пользователей для расчета долгов');
            return;
        }

        foreach ($result['userTotals'] as $ut) {
            $this->line("{$ut->user->name}: {$ut->total_sum} MDL");
        }

        $finalDebtor     = $result['debtor'];
        $finalCreditor   = $result['creditor'];
        $finalDifference = $result['finalDifference'];
        $overpayment     = $result['overpayment'];

        $existingDebt = Debt::where('date', $date)->first();

        if ($existingDebt) {
            $this->warn("Долг за {$date->translatedFormat('d F Y')} уже существует для пользователя {$finalDebtor->user->name}❗");
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

            $debtTable     = (new Debt)->getTable();
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
                if ($finalCreditor->user_id === $paidDebt['user_id']) {
                    $finalDifference += $paidDebt['paid_sum'];
                } elseif ($finalDebtor->user_id === $paidDebt['user_id']) {
                    $finalDifference -= $paidDebt['paid_sum'];
                    if ($finalDifference < 0) {
                        [$finalDebtor, $finalCreditor] = [$finalCreditor, $finalDebtor];
                        $finalDifference = abs($finalDifference);
                    }
                }
            }
        }

        if ($finalDifference === 0.0) {
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
            $formattedDifference = number_format($finalDifference, 2, '.', ' ');
            $debtMessage = __('resources.fields.notes_message.unpaid', [
                'debtor'   => $finalDebtor->user->name,
                'creditor' => $finalCreditor->user->name,
                'sum'      => $formattedDifference,
            ]);

            $attributes = [
                'date' => $date,
                'user_id' => $finalDebtor->user->id,
                'overpayment_id' => $overpayment?->id,
                'payment_status' => 'unpaid',
                'date_paid' => null,
                'partial_sum' => 0,
                'debt_sum' => $finalDifference,
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
