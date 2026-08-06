<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    | Allows Flutter web and mobile apps to reach the API from any origin.
    | In production, restrict 'allowed_origins' to your actual domains.
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost',
        'http://localhost:*',
        'http://127.0.0.1',
        'http://127.0.0.1:*',
        'https://saning-code.github.io',
        'https://de-light-api.onrender.com',
        env('FRONTEND_URL', 'https://de-light-api.onrender.com'),
    ],

    'allowed_origins_patterns' => ['.*'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
