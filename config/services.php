<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'python_api' => [
        'url' => env('PYTHON_API_URL'),
        'process_audio_url' => env('PYTHON_API_PROCESS_AUDIO_URL'),
        'predict_expenses_url' => env('PYTHON_API_PREDICT_EXPENSES_URL'),
        'health_url' => env('PYTHON_API_HEALTH_URL'),
        'token' => env('PYTHON_API_TOKEN'),
        'timeout' => (int) env('PYTHON_API_TIMEOUT', 120),
        'verify_ssl' => filter_var(env('PYTHON_API_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
