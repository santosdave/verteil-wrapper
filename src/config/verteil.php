<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Verteil API Credentials
    |--------------------------------------------------------------------------
    |
    | Your Verteil API authentication credentials
    |
    */
    'username' => env('VERTEIL_USERNAME'),
    'password' => env('VERTEIL_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Basic API configuration including base URL and timeout settings
    |
    */
    'base_url' => env('VERTEIL_BASE_URL', 'https://api.stage.verteil.com'),
    'timeout' => env('VERTEIL_TIMEOUT', 30),
    'verify_ssl' => env('VERTEIL_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for request retry behavior
    |
    */
    'retry' => [
        'max_attempts' => 3,
        'delay' => 100, // milliseconds
        'multiplier' => 2
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for response caching
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => [
            'airShopping' => 5, // minutes
            'flightPrice' => 2,
            'serviceList' => 5,
            'seatAvailability' => 2
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for different endpoints
    |
    */
    'rate_limits' => [
        'default' => [
            'requests' => 60,
            'duration' => 60 // seconds
        ],
        'airShopping' => [
            'requests' => 30,
            'duration' => 60
        ],
        'orderCreate' => [
            'requests' => 20,
            'duration' => 60
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior
    |
    */
    'logging' => [
        'channel' => 'verteil',
        'level' => env('VERTEIL_LOG_LEVEL', 'debug')
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for health monitoring and metrics collection
    |
    */
    'monitoring' => [
        'enabled' => true,
        'metrics_retention' => 24, // hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for notifications (errors, alerts, etc.)
    |
    */
    'notifications' => [
        'slack_webhook_url' => env('VERTEIL_SLACK_WEBHOOK'),
        'notification_email' => env('VERTEIL_NOTIFICATION_EMAIL')
    ]
];