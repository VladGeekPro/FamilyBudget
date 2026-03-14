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
                $minName         = $minUser->user->name;
                $maxName         = $maxUser->user->name;
                $maxDative       = self::toDative($maxName);
                $minSpent        = self::wordByGender($minName, 'потратил', 'потратила');
                $minOwes         = self::wordByGender($minName, 'должен', 'должна');
                $minPossessive   = self::wordByGender($minName, 'его', 'её');
                $overpaymentNote = "{$minName} в этом месяце {$minSpent} меньше, поэтому {$minPossessive} базовый долг перед {$maxDative} составил {$baseFormatted} MDL. Однако, по договорённости {$minName} {$minOwes} переплачивать на {$opFormatted} MDL, поэтому сейчас {$minName} {$minOwes} {$maxDative} {$finalFormatted} MDL.";
            } else {
                $finalDifference = $baseDifference - $opSum;

                if ($finalDifference < 0) {
                    [$debtor, $creditor] = [$maxUser, $minUser];
                    $userTotals = $userTotals->reverse()->values();
                    $finalDifference = abs($finalDifference);
                    $baseFormatted   = number_format($baseDifference, 2, ',', ' ');
                    $finalFormatted  = number_format($finalDifference, 2, ',', ' ');
                    $maxName         = $maxUser->user->name;
                    $minName         = $minUser->user->name;
                    $minDative       = self::toDative($minName);
                    $maxSpent        = self::wordByGender($maxName, 'потратил', 'потратила');
                    $minOwesPast     = self::wordByGender($minName, 'должен был бы', 'должна была бы');
                    $maxOwes         = self::wordByGender($maxName, 'должен', 'должна');
                    $overpaymentNote = "{$maxName} в этом месяце {$maxSpent} больше, поэтому {$minName} {$minOwesPast} оплатить {$baseFormatted} MDL. Однако, по договорённости {$maxName} {$maxOwes} переплачивать на {$opFormatted} MDL, поэтому сейчас {$maxName} {$maxOwes} {$minDative} {$finalFormatted} MDL.";
                } elseif ($finalDifference == 0.0) {
                    $baseFormatted   = number_format($baseDifference, 2, ',', ' ');
                    $maxName         = $maxUser->user->name;
                    $minName         = $minUser->user->name;
                    $maxSpent        = self::wordByGender($maxName, 'потратил', 'потратила');
                    $minOwedPast     = self::wordByGender($minName, 'должен был', 'должна была');
                    $maxOwes         = self::wordByGender($maxName, 'должен', 'должна');
                    $overpaymentNote = "{$maxName} в этом месяце {$maxSpent} больше, поэтому {$minName} изначально {$minOwedPast} {$baseFormatted} MDL. Однако, по договорённости {$maxName} {$maxOwes} переплачивать на {$opFormatted} MDL, и эта сумма полностью закрыла долг. Сейчас никто никому не должен.";
                } else {
                    $baseFormatted   = number_format($baseDifference, 2, ',', ' ');
                    $finalFormatted  = number_format($finalDifference, 2, ',', ' ');
                    $maxName         = $maxUser->user->name;
                    $minName         = $minUser->user->name;
                    $maxDative       = self::toDative($maxName);
                    $maxSpent        = self::wordByGender($maxName, 'потратил', 'потратила');
                    $minOwesPast     = self::wordByGender($minName, 'должен был бы', 'должна была бы');
                    $minOwesNow      = self::wordByGender($minName, 'должен', 'должна');
                    $maxOwes         = self::wordByGender($maxName, 'должен', 'должна');
                    $overpaymentNote = "{$maxName} в этом месяце {$maxSpent} больше, поэтому {$minName} {$minOwesPast} оплатить {$baseFormatted} MDL. Однако, по договорённости {$maxName} {$maxOwes} переплачивать на {$opFormatted} MDL, поэтому сейчас {$minName} {$minOwesNow} {$maxDative} {$finalFormatted} MDL.";
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

    private static function getGender(string $name): string
    {
        $last2 = mb_substr($name, -2, null, 'UTF-8');
        $last1 = mb_substr($name, -1, null, 'UTF-8');
        if (mb_strtolower($last2, 'UTF-8') === 'ия') return 'female';
        if (in_array(mb_strtolower($last1, 'UTF-8'), ['а', 'я'], true)) return 'female';
        return 'male';
    }

    private static function wordByGender(string $name, string $male, string $female): string
    {
        return self::getGender($name) === 'female' ? $female : $male;
    }

    private static function toDative(string $name): string
    {
        $lower = mb_strtolower($name, 'UTF-8');
        $last2 = mb_substr($lower, -2, null, 'UTF-8');
        $last1 = mb_substr($lower, -1, null, 'UTF-8');
        if ($last2 === 'ия') return mb_substr($name, 0, -2, 'UTF-8') . 'ии';
        if ($last1 === 'а')  return mb_substr($name, 0, -1, 'UTF-8') . 'е';
        if ($last1 === 'я')  return mb_substr($name, 0, -1, 'UTF-8') . 'е';
        if ($last2 === 'ий') return mb_substr($name, 0, -2, 'UTF-8') . 'ию';
        if ($last1 === 'й')  return mb_substr($name, 0, -1, 'UTF-8') . 'ю';
        if ($last1 === 'ь')  return mb_substr($name, 0, -1, 'UTF-8') . 'ю';
        return $name . 'у';
    }
}
