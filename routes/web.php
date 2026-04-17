<?php

use App\Http\Controllers\ExpenseVoiceTranscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->post('/expense-voice/transcriptions', ExpenseVoiceTranscriptionController::class)
    ->name('expense-voice.transcribe');

