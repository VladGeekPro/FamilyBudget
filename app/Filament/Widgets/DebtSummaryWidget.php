<?php

namespace App\Filament\Widgets;

use App\Services\DebtCalculationService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

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

        return Cache::remember('widget:debt_summary', now()->addSeconds(120), function () use ($start, $end, $today): array {
            $result = DebtCalculationService::calculate($start, $end);

            $daysInMonth   = (int) $end->day;
            $daysElapsed   = (int) $today->day;
            $daysRemaining = $daysInMonth - $daysElapsed;
            $monthProgress = round(($daysElapsed / $daysInMonth) * 100);
            $monthLabel    = $start->translatedFormat('F Y');

            if (! $result['hasEnoughUsers']) {
                return [
                    'noData'          => true,
                    'start'           => $start,
                    'end'             => $end,
                    'today'           => $today,
                    'monthLabel'      => $monthLabel,
                    'daysInMonth'     => $daysInMonth,
                    'daysElapsed'     => $daysElapsed,
                    'daysRemaining'   => $daysRemaining,
                    'monthProgress'   => $monthProgress,
                    'userTotals'      => $result['userTotals'],
                    'finalDebtor'     => null,
                    'finalCreditor'   => null,
                    'finalDifference' => 0.0,
                    'baseDifference'  => 0.0,
                    'overpayment'     => null,
                    'overpaymentNote' => null,
                    'isSettled'       => false,
                    'totalSpent'      => 0.0,
                ];
            }

            return [
                'noData'          => false,
                'start'           => $start,
                'end'             => $end,
                'today'           => $today,
                'monthLabel'      => $monthLabel,
                'daysInMonth'     => $daysInMonth,
                'daysElapsed'     => $daysElapsed,
                'daysRemaining'   => $daysRemaining,
                'monthProgress'   => $monthProgress,
                'userTotals'      => $result['userTotals'],
                'totalSpent'      => $result['totalSpent'],
                'baseDifference'  => $result['baseDifference'],
                'overpayment'     => $result['overpayment'],
                'overpaymentNote' => $result['overpaymentNote'],
                'finalDebtor'     => $result['debtor'],
                'finalCreditor'   => $result['creditor'],
                'finalDifference' => $result['finalDifference'],
                'isSettled'       => $result['isSettled'],
            ];
        });
    }
}
