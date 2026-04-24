<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class ExpensePredictionService extends BasePythonApiService
{
    /**
     * @return array<string, mixed>
     */
    public function predictForUser(int $userId): array
    {
        $predictUrl = $this->predictUrl();

        if (blank($predictUrl)) {
            throw new RuntimeException('Prediction API URL is not configured.');
        }

        $expenses = Expense::query()
            ->select(['date', 'sum', 'supplier_id', 'user_id'])
            ->whereDate('date', '>=', now()->subMonths(12)->startOfDay()->toDateString())
            ->orderByDesc('date')
            ->limit(1000)
            ->get()
            ->map(static fn (Expense $expense): array => [
                'date' => $expense->date?->format('Y-m-d') ?? (string) $expense->date,
                'sum' => (float) $expense->sum,
                'supplier_id' => (int) $expense->supplier_id,
                'user_id' => (int) $expense->user_id,
            ])
            ->values()
            ->all();

        $this->warmUpApi();

        try {
            $response = $this->client()->post($predictUrl, [
                'expenses' => $expenses,
                'user_id' => $userId,
            ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Prediction API is unavailable.', previous: $exception);
        }

        $responseData = $response->json();

        if (is_array($responseData)) {
            return [
                'payload' => $responseData,
                'status' => $response->status(),
            ];
        }

        return [
            'payload' => [
                'status' => $response->successful() ? 'ok' : 'error',
                'message' => $response->body(),
            ],
            'status' => $response->status(),
        ];
    }

    protected function predictUrl(): ?string
    {
        $url = config('services.python_api.predict_expenses_url');

        return filled($url) ? rtrim((string) $url, '/') : null;
    }
}