<?php

use App\Http\Controllers\ExpenseVoiceTranscriptionDebugController;
use App\Http\Controllers\ExpenseVoiceTranscriptionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/login');

Route::get('/debug/expense-voice/transcribe', ExpenseVoiceTranscriptionDebugController::class)
    ->name('debug.expense-voice.transcribe');

Route::middleware('auth')->post('/expense-voice/transcriptions', ExpenseVoiceTranscriptionController::class)
    ->name('expense-voice.transcribe');

