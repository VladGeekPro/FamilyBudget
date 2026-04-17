<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class ExpenseVoiceTranscriptionService
{
    public function transcribe(UploadedFile $audioFile, string $mode): array
    {
        $context = $this->buildContext();
        $pythonResult = $this->runFastApiTranscription(
            audioFile: $audioFile,
            mode: $mode,
            context: $context,
        );

        $fieldUpdates = $this->mapFieldUpdates($mode, $pythonResult, $context);

        if ($fieldUpdates === []) {
            throw new RuntimeException(__('resources.voice.notifications.empty_result'));
        }

        return [
            'message' => __('resources.voice.notifications.applied'),
            'mode' => $mode,
            'transcript' => $pythonResult['text'] ?? null,
            'field_updates' => $fieldUpdates,
            'meta' => [
                'supplier' => $pythonResult['supplier'] ?? null,
                'user' => $pythonResult['user'] ?? null,
                'date' => $pythonResult['date'] ?? null,
                'sum' => $pythonResult['sum'] ?? null,
            ],
        ];
    }

    protected function buildContext(): array
    {
        $users = User::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();

        $suppliers = Supplier::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
            ])
            ->all();

        return [
            'users' => $users,
            'suppliers' => $suppliers,
        ];
    }

    protected function runFastApiTranscription(UploadedFile $audioFile, string $mode, array $context): array
    {
        $url = config('expense_voice.fastapi.url');

        if (blank($url)) {
            throw new RuntimeException(__('resources.voice.notifications.fastapi_url_missing'));
        }

        try {
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(__('resources.voice.notifications.invalid_payload'), previous: $exception);
        }

        $client = Http::acceptJson()
            ->connectTimeout((int) config('expense_voice.fastapi.connect_timeout', 8))
            ->timeout((int) config('expense_voice.fastapi.timeout', 120))
            ->retry(
                (int) config('expense_voice.fastapi.retries', 2),
                (int) config('expense_voice.fastapi.retry_sleep_ms', 250),
            )
            ->withOptions([
                'verify' => (bool) config('expense_voice.fastapi.verify_ssl', false),
            ]);

        $token = config('expense_voice.fastapi.token');
        if (filled($token)) {
            $client = $client->withToken($token);
        }

        $audioPath = $audioFile->getRealPath();
        $audioContent = $audioPath ? file_get_contents($audioPath) : false;

        if ($audioContent === false) {
            throw new RuntimeException(__('resources.voice.notifications.fastapi_failed'));
        }

        $fileName = $audioFile->getClientOriginalName() ?: 'voice.webm';

        try {
            $response = $client
                ->attach('audio', $audioContent, $fileName)
                ->post($url, [
                    'mode' => $mode,
                    'context' => $contextJson,
                ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                __('resources.voice.notifications.fastapi_failed') . ' ' . $exception->getMessage(),
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                $response->json('message')
                    ?? $response->body()
                    ?? __('resources.voice.notifications.fastapi_failed')
            );
        }

        $decoded = $response->json();

        try {
            if (! is_array($decoded)) {
                throw new JsonException('Response is not a JSON object.');
            }
        } catch (JsonException $exception) {
            throw new RuntimeException(__('resources.voice.notifications.invalid_response'));
        }

        if (($decoded['status'] ?? 'ok') !== 'ok') {
            throw new RuntimeException($decoded['message'] ?? __('resources.voice.notifications.fastapi_failed'));
        }

        return $decoded;
    }

    protected function mapFieldUpdates(string $mode, array $pythonResult, array $context): array
    {
        if ($mode === 'notes') {
            $notes = $this->normalizeNotesText($pythonResult['notes'] ?? $pythonResult['text'] ?? null);

            return $this->filterEmptyValues([
                'notes' => $notes,
            ]);
        }

        $supplierId = $this->matchEntityId($pythonResult['supplier'] ?? null, $context['suppliers']);

        return $this->filterEmptyValues([
            'user_id' => $this->matchEntityId($pythonResult['user'] ?? null, $context['users']),
            'date' => $this->normalizeDate($pythonResult['date'] ?? null),
            'supplier_id' => $supplierId,
            'sum' => $this->normalizeAmount($pythonResult['sum'] ?? null),
            'notes' => $this->normalizeNotesText($pythonResult['notes'] ?? $pythonResult['text'] ?? null),
        ]);
    }

    protected function matchEntityId(?string $value, array $items): ?int
    {
        if (blank($value)) {
            return null;
        }

        $needle = $this->normalizeName($value);
        $bestMatchId = null;
        $bestMatchScore = 0;

        foreach ($items as $item) {
            $candidate = $this->normalizeName($item['name'] ?? null);

            if ($candidate === '') {
                continue;
            }

            if ($candidate === $needle) {
                return $item['id'];
            }

            similar_text($needle, $candidate, $score);

            if (str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
                $score += 20;
            }

            if ($score > $bestMatchScore) {
                $bestMatchScore = $score;
                $bestMatchId = $item['id'];
            }
        }

        return $bestMatchScore >= 55 ? $bestMatchId : null;
    }

    protected function normalizeDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeAmount(string | float | int | null $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace([' ', ','], ['', '.'], (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    protected function normalizeNotesText(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return Str::of($normalized)->trim()->toString();
    }

    protected function normalizeName(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        return (string) Str::of($value)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]+/u', ' ')
            ->squish();
    }

    protected function filterEmptyValues(array $values): array
    {
        return array_filter($values, static fn ($value) => filled($value) || $value === 0 || $value === '0');
    }
}