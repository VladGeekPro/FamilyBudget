<?php

use App\Http\Controllers\ExpenseVoiceTranscriptionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/login');

Route::middleware('auth')->post('/expense-voice/transcriptions', ExpenseVoiceTranscriptionController::class)
    ->name('expense-voice.transcribe');

