<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Overpayment;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class DebtSummaryWidget extends Widget
{
    protected string $view = 'filament.widgets.debt-summary-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $start = now()->startOfMonth();
        $end   = now()->endOfMonth();
        $today = now();

        $daysInMonth   = (int) $end->day;
        $daysElapsed   = (int) $today->day;
        $daysRemaining = $daysInMonth - $daysElapsed;
        $monthProgress = round(($daysElapsed / $daysInMonth) * 100);

        // ---------- expenses per user ----------
        $allUsers = User::orderBy('name')->get();

        $rawExpenses = Expense::selectRaw('user_id, COALESCE(SUM(sum), 0) as total_sum, COUNT(*) as tx_count')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $userTotals = $allUsers->map(fn(User $u) => (object)[
            'user_id'   => $u->id,
            'user'      => $u,
            'total_sum' => (float) ($rawExpenses->get($u->id)?->total_sum ?? 0),
            'tx_count'  => (int) ($rawExpenses->get($u->id)?->tx_count ?? 0),
        ])->values();

        if ($userTotals->count() < 2) {
            return compact(
                'start', 'end', 'today', 'daysInMonth',
                'daysElapsed', 'daysRemaining', 'monthProgress',
                'userTotals',
            ) + [
                'noData'         => true,
                'finalDebtor'    => null,
                'finalCreditor'  => null,
                'finalDifference'=> 0.0,
                'baseDifference' => 0.0,
                'overpayment'    => null,
                'overpaymentNote'=> null,
                'isSettled'      => false,
                'totalSpent'     => 0.0,
                'monthLabel'     => now()->translatedFormat('F Y'),
            ];
        }

        $sorted  = $userTotals->sortBy('total_sum')->values();
        $minUser = $sorted->first(); // less spent → debtor
        $maxUser = $sorted->last();  // more spent → creditor

        $totalSpent    = $userTotals->sum('total_sum');
        $baseDifference = ($maxUser->total_sum - $minUser->total_sum) / 2;

        // ---------- overpayment ----------
        $overpayment      = Overpayment::where('created_at', '<=', $end)->orderByDesc('created_at')->first();
        $finalDebtor      = $minUser;
        $finalCreditor    = $maxUser;
        $finalDifference  = $baseDifference;
        $overpaymentNote  = null;

        if ($overpayment) {
            $opSum = (float) $overpayment->sum;
            if ($minUser->user_id === $overpayment->user_id) {
                $finalDifference = $baseDifference + $opSum;
                $overpaymentNote = '+' . number_format($opSum, 2, ',', ' ') . ' MDL (переплата добавлена к долгу)';
            } else {
                $finalDifference = $baseDifference - $opSum;
                if ($finalDifference < 0) {
                    $finalDebtor     = $maxUser;
                    $finalCreditor   = $minUser;
                    $finalDifference = abs($finalDifference);
                    $overpaymentNote = 'Направление долга изменилось (переплата ' . number_format($opSum, 2, ',', ' ') . ' MDL > базового долга)';
                } else {
                    $overpaymentNote = '–' . number_format($opSum, 2, ',', ' ') . ' MDL (переплата вычтена из долга)';
                }
            }
        }

        $isSettled = $finalDifference < 0.01;

        return [
            'noData'          => false,
            'start'           => $start,
            'end'             => $end,
            'today'           => $today,
            'monthLabel'      => $start->translatedFormat('F Y'),
            'daysInMonth'     => $daysInMonth,
            'daysElapsed'     => $daysElapsed,
            'daysRemaining'   => $daysRemaining,
            'monthProgress'   => $monthProgress,
            'userTotals'      => $userTotals,
            'totalSpent'      => $totalSpent,
            'baseDifference'  => $baseDifference,
            'overpayment'     => $overpayment,
            'overpaymentNote' => $overpaymentNote,
            'finalDebtor'     => $finalDebtor,
            'finalCreditor'   => $finalCreditor,
            'finalDifference' => $finalDifference,
            'isSettled'       => $isSettled,
        ];
    }
}
