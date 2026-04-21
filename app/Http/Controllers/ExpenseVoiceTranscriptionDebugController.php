<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseVoiceTranscriptionRequest;
use App\Services\PythonApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class ExpenseVoiceTranscriptionDebugController extends Controller
{
    public function __invoke(PythonApiService $service): JsonResponse
    {
        $baseUrl = (string) (config('services.python_api.url') ?? '');
        $processUrl = (string) (config('services.python_api.process_audio_url') ?? '');

        if (blank($processUrl)) {
            $baseUrl = $baseUrl !== '' ? rtrim($baseUrl, '/') : 'https://vladret2026-convertaudiotojson.hf.space';

            config([
                'services.python_api.url' => $baseUrl,
                'services.python_api.process_audio_url' => $baseUrl . '/process-audio',
                'services.python_api.health_url' => $baseUrl . '/health',
            ]);
        }

        $audioPath = app_path('Http/Controllers/1.mp3');

        if (! is_file($audioPath)) {
            throw new RuntimeException('Debug audio file not found: ' . $audioPath);
        }

        $uploaded = new UploadedFile(
            $audioPath,
            basename($audioPath),
            mime_content_type($audioPath) ?: 'audio/mpeg',
            test: true,
        );

        $baseRequest = Request::create(
            uri: '/expense-voice/transcriptions',
            method: 'POST',
            parameters: ['mode' => 'expense'],
            files: ['audio' => $uploaded],
        );

        $request = ExpenseVoiceTranscriptionRequest::createFromBase($baseRequest);

        return app(ExpenseVoiceTranscriptionController::class)->__invoke($request, $service);
    }
}
