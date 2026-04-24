<?php

use App\Http\Controllers\ExpenseRecommendationController;
use App\Http\Controllers\ExpenseVoiceTranscriptionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/login');

Route::middleware('auth')->group(function (): void {

    Route::post('/expense-voice/transcriptions', ExpenseVoiceTranscriptionController::class)
        ->name('expense-voice.transcribe');

    Route::post('/expense-recommendations/predict', [ExpenseRecommendationController::class, 'predict'])
        ->name('expense-recommendations.predict');

    Route::post('/expense-recommendations/expenses', [ExpenseRecommendationController::class, 'store'])
        ->name('expense-recommendations.expenses.store');
});
