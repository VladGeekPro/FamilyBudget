<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Overpayment;
use App\Models\User;
use Carbon\Carbon;

class DebtCalculationService
{
    public static function calculate(Carbon $from, Carbon $to): array
    {
        $dateRange = [$from->toDateString(), $to->toDateString()];

        $allUsers = User::orderBy('name')->get();

        $rawExpenses = Expense::selectRaw('user_id, SUM(sum) as total_sum, COUNT(*) as tx_count')
            ->whereBetween('date', $dateRange)
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $userTotals = $allUsers->map(fn(User $u) => (object) [
            'user_id'   => $u->id,
            'user'      => $u,
            'total_sum' => $rawExpenses->get($u->id)?->total_sum ?? 0,
            'tx_count'  => $rawExpenses->get($u->id)?->tx_count  ?? 0,
        ])
        ->sortBy('total_sum')
        ->values();

        if ($userTotals->count() < 2) {
            return [
                'hasEnoughUsers' => false,
                'userTotals' => $userTotals,
                'totalSpent' => 0.0,
                'baseDifference' => 0.0,
                'finalDifference' => 0.0,
                'isSettled' => false,
                'debtor' => null,
                'creditor' => null,
                'overpayment' => null,
                'overpaymentNote' => null,
            ];
        }

        $minUser = $userTotals->first();
        $maxUser = $userTotals->last();

        $totalSpent     = $userTotals->sum('total_sum');
        $baseDifference = ($maxUser->total_sum - $minUser->total_sum) / 2;

        $overpayment = Overpayment::where('created_at', '<=', $to)
            ->orderByDesc('created_at')
            ->first();

        $debtor          = $minUser;
        $creditor        = $maxUser;
        $finalDifference = $baseDifference;
        $overpaymentNote = null;

        if ($overpayment) {
            $opSum       = $overpayment->sum;
            $opFormatted = number_format($opSum, 2, ',', ' ');

            if ($minUser->user_id === $overpayment->user_id) {
                $finalDifference = $baseDifference + $opSum;
                $baseFormatted   = number_format($baseDifference, 2, ',', ' ');
                $finalFormatted  = number_format($finalDifference, 2, ',', ' ');
                $overpaymentNote = "Ранее {$minUser->user->name} заплатил(а) на {$opFormatted} MDL больше договорённого — эта сумма прибавлена к долгу ({$baseFormatted} + {$opFormatted} = {$finalFormatted} MDL)";
            } else {
                $finalDifference = $baseDifference - $opSum;

                if ($finalDifference < 0) {
                    [$debtor, $creditor] = [$maxUser, $minUser];
                    $finalDifference = abs($finalDifference);
                    $baseFormatted   = number_format($baseDifference, 2, ',', ' ');
                    $finalFormatted  = number_format($finalDifference, 2, ',', ' ');
                    $overpaymentNote = "Переплата {$maxUser->user->name} ({$opFormatted} MDL) оказалась больше разрыва в расходах ({$baseFormatted} MDL) — теперь {$debtor->user->name} должен(на) вернуть разницу {$finalFormatted} MDL";
                } else {
                    $baseFormatted  = number_format($baseDifference, 2, ',', ' ');
                    $finalFormatted = number_format($finalDifference, 2, ',', ' ');
                    $overpaymentNote = "Прошлая переплата {$maxUser->user->name} ({$opFormatted} MDL) зачтена в счёт долга: {$baseFormatted} − {$opFormatted} = {$finalFormatted} MDL";
                }
            }
        }

        $isSettled = $finalDifference === 0.0;

        return [
            'hasEnoughUsers' => true,
            'userTotals' => $userTotals,
            'totalSpent' => $totalSpent,
            'baseDifference' => $baseDifference,
            'finalDifference' => $finalDifference,
            'isSettled' => $isSettled,
            'debtor' => $debtor,
            'creditor' => $creditor,
            'overpayment' => $overpayment,
            'overpaymentNote' => $overpaymentNote,
        ];
    }
}
