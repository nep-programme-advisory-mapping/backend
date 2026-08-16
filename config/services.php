<?php

return [

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

    'imagekit' => [
        'public_key'   => env('IMAGEKIT_PUBLIC_KEY'),
        'private_key'  => env('IMAGEKIT_PRIVATE_KEY'),
        'url_endpoint' => env('IMAGEKIT_URL_ENDPOINT'),
    ],

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-5'),
        'timeout' => env('CLAUDE_TIMEOUT', 30),
        'retry_attempts' => env('CLAUDE_RETRY_ATTEMPTS', 2),
        'retry_delay' => env('CLAUDE_RETRY_DELAY', 500),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'timeout' => env('GROQ_TIMEOUT', 30),
        'retry_attempts' => env('GROQ_RETRY_ATTEMPTS', 2),
        'retry_delay' => env('GROQ_RETRY_DELAY', 500),
    ]
];
