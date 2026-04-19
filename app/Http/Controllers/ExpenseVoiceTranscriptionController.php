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
        // $mode = $request->string('mode')->toString();
        $mode = "expense";

        try {
            // $result = $service->transcribe(
            //     audioFile: $request->file('audio'),
            //     mode: $mode,
            // );
            $audioPath = app_path("Http/Controllers/1.mp3");
            $result = $service->transcribe(
                audioFile: new \Illuminate\Http\UploadedFile(
                    $audioPath,
                    basename($audioPath),
                    mime_content_type($audioPath) ?: 'audio/mpeg',
                    test: true,
                ),
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
