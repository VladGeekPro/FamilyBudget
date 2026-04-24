<?php

namespace App\Services;

use GuzzleHttp\Handler\StreamHandler;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

abstract class BasePythonApiService
{
    protected function warmUpApi(): bool
    {
        $healthUrl = $this->healthUrl();

        if (blank($healthUrl)) {
            return false;
        }

        $cacheKey = 'python-api:warmed:' . sha1($healthUrl);

        if (Cache::get($cacheKey) === true) {
            return true;
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                if ($this->client()->get($healthUrl)->successful()) {
                    Cache::put($cacheKey, true, now()->addSeconds(90));

                    return true;
                }
            } catch (\Throwable) {
            }

            if ($attempt < 3) {
                usleep(1000000);
            }
        }

        return false;
    }

    protected function client(): PendingRequest
    {
        $client = Http::acceptJson()
            ->timeout((int) config('services.python_api.timeout', 120))
            ->withOptions([
                'verify' => (bool) config('services.python_api.verify_ssl', true),
                'handler' => new StreamHandler(),
            ]);

        $token = config('services.python_api.token');

        if (filled($token)) {
            $client = $client->withToken($token);
        }

        return $client;
    }

    protected function baseUrl(): ?string
    {
        $url = config('services.python_api.url');

        return filled($url) ? rtrim((string) $url, '/') : null;
    }

    protected function healthUrl(): ?string
    {
        $url = config('services.python_api.health_url');

        if (filled($url)) {
            return rtrim((string) $url, '/');
        }

        $baseUrl = $this->baseUrl();

        if (filled($baseUrl)) {
            return $baseUrl . '/health';
        }

        $predictUrl = config('services.python_api.predict_expenses_url');

        if (! filled($predictUrl)) {
            return null;
        }

        $parts = parse_url((string) $predictUrl);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return sprintf('%s://%s%s/health', $parts['scheme'], $parts['host'], $port);
    }
}