<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Providers (system default fallback)
    |--------------------------------------------------------------------------
    | Optional. When a user has no provider configured yet, the first
    | system default below is seeded into their account. Users can
    | configure their own providers via Settings.
    */

    'ai' => [
        'default_base_url' => env('AI_DEFAULT_BASE_URL'),
        'default_api_key' => env('AI_DEFAULT_API_KEY'),
        'default_model' => env('AI_DEFAULT_MODEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing inertia packages to
    | have a conventional file to locate the given service credentials.
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

];
