<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseVoiceTranscriptionRequest;
use App\Services\ExpenseVoiceTranscriptionService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class ExpenseVoiceTranscriptionController extends Controller
{
    public function __invoke(
        ExpenseVoiceTranscriptionRequest $request,
        ExpenseVoiceTranscriptionService $service,
    ): JsonResponse {
        $mode = $request->string('mode')->toString();

        try {
            $result = $service->transcribe(
                audioFile: $request->file('audio'),
                mode: $mode,
            );
            return response()->json([
                ...$result,
                'title' => __('resources.voice.notifications.applied_title'),
                'body' => __('resources.voice.notifications.applied_body.' . $mode),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'title' => __('resources.voice.notifications.failed_title'),
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'title' => __('resources.voice.notifications.failed_title'),
                'message' => __('resources.voice.notifications.unexpected_error'),
            ], 500);
        }
    }
}
