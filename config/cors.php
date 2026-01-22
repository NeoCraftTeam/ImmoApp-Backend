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

    'paths' => ['api/*', 'auth/*', 'sanctum/csrf-cookie', 'login', 'register'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000', // front
        'http://localhost:8000', // localhost
        'http://localhost:5173', // localhost
        'https://keyhome.neocraft.dev', // prod
        'http://127.0.0.1:3000',
    ],

    'allowed_origins_patterns' => ['.*'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,  // 24 heures (améliore les performances)

    'supports_credentials' => true,

];
