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

    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'geniuspay'),

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

        'geniuspay' => [
            'api_key' => env('GENIUSPAY_API_KEY'),
            'api_secret' => env('GENIUSPAY_API_SECRET'),
            'webhook_secret' => env('GENIUSPAY_WEBHOOK_SECRET'),
            'base_url' => env('GENIUSPAY_BASE_URL', 'https://pay.genius.ci/api/v1/merchant'),
            'redirect_url' => env('GENIUSPAY_REDIRECT_URL'),
            'default_country' => env('GENIUSPAY_DEFAULT_COUNTRY', 'CM'),
        ],

        // Legacy Flutterwave — kept for reference / unmigrated rows only (no longer routed).
        'flutterwave' => [
            'public_key' => env('FLW_PUBLIC_KEY'),
            'secret_key' => env('FLW_SECRET_KEY'),
            'encryption_key' => env('FLW_ENCRYPTION_KEY'),
            'webhook_secret' => env('FLW_WEBHOOK_SECRET'),
            'base_url' => env('FLW_BASE_URL', 'https://api.flutterwave.com/v3'),
            'redirect_url' => env('FLW_REDIRECT_URL'),
            'logo' => env('APP_LOGO_URL'),
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
    | GeniusPay payment_method codes (optional — omit for hosted checkout)
    |--------------------------------------------------------------------------
    */

    'geniuspay_payment_methods' => [
        'mobile_money' => 'mtn_money',
        'orange_money' => 'orange_money',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flutterwave Payment Options by Method (legacy — unmigrated installs only)
    |--------------------------------------------------------------------------
    */

    'flutterwave_payment_options' => [
        'mobile_money' => 'mobilemoneycameroon',
        'orange_money' => 'mobilemoneycameroon',
        'flutterwave' => 'card,mobilemoneycameroon,mobilemoneyfranco',
        'card' => 'card',
    ],

];
