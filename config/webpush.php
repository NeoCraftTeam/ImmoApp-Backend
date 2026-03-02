<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VAPID Keys
    |--------------------------------------------------------------------------
    |
    | These are the keys for authentication (VAPID).
    | These keys must be safely stored and should not change.
    |
    */

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@keyhome.app'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Subscription Model
    |--------------------------------------------------------------------------
    */

    'model' => \App\Models\PushSubscription::class,

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    */

    'table_name' => env('WEBPUSH_DB_TABLE', 'push_subscriptions'),

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    */

    'database_connection' => env('WEBPUSH_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    /*
    |--------------------------------------------------------------------------
    | Guzzle Client Options
    |--------------------------------------------------------------------------
    */

    'client_options' => [],

    /*
    |--------------------------------------------------------------------------
    | Automatic Padding
    |--------------------------------------------------------------------------
    |
    | Set to false to support Firefox Android with v1 endpoint.
    |
    */

    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),

];
