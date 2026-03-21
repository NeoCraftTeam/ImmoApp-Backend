<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'auth/*', 'sanctum/csrf-cookie', 'login', 'register', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        ...((string) env('APP_ENV') !== 'production' ? [
            'http://localhost:3000',
            'http://192.168.1.186:3000',
            'http://192.168.1.130:3000',
            'https://keyhome.test',
        ] : []),
        'https://api.keyhome.neocraft.dev',
        'https://keyhome.fr',
        'https://www.keyhome.fr',
        'https://keyhome.app',
        'https://www.keyhome.app',
        'https://neocraft.dev',
        'https://www.neocraft.dev',
    ]),

    'allowed_origins_patterns' => [
        '/^https:\/\/[a-z0-9-]+\.keyhome\.neocraft\.dev$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,  // 24 heures (améliore les performances)

    'supports_credentials' => true,

];
