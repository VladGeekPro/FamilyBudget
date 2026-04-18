<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseVoiceTranscriptionRequest;
use App\Services\PythonApiService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class ExpenseVoiceTranscriptionController extends Controller
{
    public function __invoke(
        ExpenseVoiceTranscriptionRequest $request,
        PythonApiService $service,
    ): JsonResponse {
        try {
            $result = $service->transcribe(
                audioFile: $request->file('audio'),
                mode: $request->string('mode')->toString(),
            );

            return response()->json($result);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => __('resources.voice.notifications.unexpected_error'),
            ], 500);
        }
    }
}