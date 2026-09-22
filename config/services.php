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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'intasend' => [
        'test' => env('INTASEND_TEST_ENVIRONMENT', true),

        'publishable_key' => env('INTASEND_PUBLISHABLE_KEY'),

        'secret_key' => env('INTASEND_SECRET_KEY'),

        'premium_amount' => env('PESAPULSE_PREMIUM_AMOUNT'),

        'premium_currency' => env(
        'PESAPULSE_PREMIUM_CURRENCY',
        'KES'
        ),

        'webhook_challenge' => env(
        'INTASEND_WEBHOOK_CHALLENGE'
        ),

        'premium_duration_days' => (int) env(
        'PESAPULSE_PREMIUM_DURATION_DAYS',
        30
        ),
    ],

];
