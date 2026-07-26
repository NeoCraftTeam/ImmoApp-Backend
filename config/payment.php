<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | The payment gateway used for all transactions.
    |
    */

    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'kpay'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Payment Gateway
    |--------------------------------------------------------------------------
    |
    | When the primary gateway fails, this gateway is tried automatically.
    | Set to null to disable fallback behaviour.
    |
    */

    'fallback' => env('PAYMENT_FALLBACK_GATEWAY', null),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways Configuration
    |--------------------------------------------------------------------------
    */

    'gateways' => [

        'kpay' => [
            'api_key' => env('KPAY_API_KEY'),
            'api_secret' => env('KPAY_API_SECRET'),
            'webhook_secret' => env('KPAY_WEBHOOK_SECRET'),
            'base_url' => env('KPAY_BASE_URL', 'https://admin.kpay.site'),
            'redirect_url' => env('KPAY_REDIRECT_URL'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    | KeyHome is launching in francophone sub-Saharan Africa (XAF/XOF) but is
    | being engineered for global expansion. The list below documents every
    | currency the application is allowed to accept; minor-unit info lives in
    | `config('currencies.locale_map')` for formatting.
    */

    'supported_currencies' => [
        // Africa (launch markets)
        'XAF', 'XOF', 'GHS', 'NGN', 'KES', 'TZS', 'UGX', 'ZAR', 'MAD', 'EGP',
        // Europe
        'EUR', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN',
        // Americas
        'USD', 'CAD', 'BRL', 'MXN',
        // Asia / Pacific
        'AED', 'SAR', 'INR', 'CNY', 'JPY', 'AUD', 'NZD', 'SGD', 'HKD',
    ],

    'default_currency' => env('PAYMENT_DEFAULT_CURRENCY', 'XAF'),

    /*
    |--------------------------------------------------------------------------
    | Mobile Money Operators by Country
    |--------------------------------------------------------------------------
    */

    'mobile_money_operators' => [
        'cameroon' => ['mtn_cm', 'orange_cm'],
        'senegal' => ['orange_sn', 'free_sn'],
        'ghana' => ['mtn_gh', 'vodafone_gh', 'airtel_tigo_gh'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale pending reconciliation
    |--------------------------------------------------------------------------
    |
    | Pending payments older than this many hours are re-verified then marked
    | failed when the gateway still reports no success (abandoned checkouts).
    |
    */

    'stale_pending_hours' => (int) env('PAYMENT_STALE_PENDING_HOURS', 6),

];
