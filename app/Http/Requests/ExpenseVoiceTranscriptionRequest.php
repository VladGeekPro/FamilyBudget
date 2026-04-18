<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;

class ExpenseVoiceTranscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:expense,notes'],
            'audio' => [
                'required',
                File::types(['webm', 'wav', 'mp3', 'm4a', 'ogg'])
                    ->max(12288),
            ],
        ];
    }
}