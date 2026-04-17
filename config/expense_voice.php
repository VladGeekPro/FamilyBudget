<?php

return [
    'max_upload_kb' => (int) env('EXPENSE_VOICE_MAX_UPLOAD_KB', 12288),
    'fastapi' => [
        'url' => env('EXPENSE_VOICE_FASTAPI_URL', 'http://127.0.0.1:8000/process-audio'),
        'connect_timeout' => (int) env('EXPENSE_VOICE_FASTAPI_CONNECT_TIMEOUT', 8),
        'timeout' => (int) env('EXPENSE_VOICE_FASTAPI_TIMEOUT', 120),
        'retries' => (int) env('EXPENSE_VOICE_FASTAPI_RETRIES', 2),
        'retry_sleep_ms' => (int) env('EXPENSE_VOICE_FASTAPI_RETRY_SLEEP_MS', 250),
        'token' => env('EXPENSE_VOICE_FASTAPI_TOKEN'),
        'verify_ssl' => filter_var(env('EXPENSE_VOICE_FASTAPI_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
    ],
];