<?php

return [
    'api_key' => env('HOUSEPEDIA_API_KEY'),

    'base_url' => env('HOUSEPEDIA_BASE_URL'),

    'version' => env('HOUSEPEDIA_VERSION', 'v5'),

    'language' => env('HOUSEPEDIA_LANGUAGE'),

    'timeout' => env('HOUSEPEDIA_TIMEOUT', 30),

    'connect_timeout' => env('HOUSEPEDIA_CONNECT_TIMEOUT', 10),

    'retry' => [
        'retries' => env('HOUSEPEDIA_RETRIES', 2),

        'service_unavailable' => env('HOUSEPEDIA_RETRY_SERVICE_UNAVAILABLE', false),
    ],

    'cache' => [
        'enabled' => env('HOUSEPEDIA_CACHE', false),

        'store' => env('HOUSEPEDIA_CACHE_STORE'),

        'prefix' => env('HOUSEPEDIA_CACHE_PREFIX', 'housepedia-data-intelligence'),

        'max_age_in_seconds' => env('HOUSEPEDIA_CACHE_MAX_AGE'),
    ],
];
