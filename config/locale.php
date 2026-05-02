<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Locale & Internationalisation
|--------------------------------------------------------------------------
|
| KeyHome is launching francophone (fr_FR) but is engineered for global
| roll-out. This file is the single source of truth for:
|   - Supported UI locales (frontend chooses from this list)
|   - Default locale + fallback
|   - Currency ↔ locale mapping for number / price formatting
|   - Acceptable IANA timezones
|
| Backend services should read these values via `config('locale.*')`
| instead of hard-coding `fr_FR` / `XAF` anywhere in the codebase.
*/

return [

    'default' => env('APP_LOCALE', 'fr'),

    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Supported UI locales (BCP-47 short codes)
    |--------------------------------------------------------------------------
    | Used by the `LocaleResolver` middleware (Accept-Language) and exposed
    | to the frontend via `/api/v1/me`.
    */
    'supported' => [
        'fr', // launch language — francophone sub-Saharan Africa
        'en', // global English (priority #2)
        'pt', // Lusophone Africa (Angola, Mozambique, Cape Verde) + Brazil
        'es', // future: Latin America
        'ar', // future: MENA (RTL — frontend has dedicated handling)
    ],

    /*
    |--------------------------------------------------------------------------
    | RTL locales
    |--------------------------------------------------------------------------
    */
    'rtl' => ['ar', 'he', 'fa', 'ur'],

    /*
    |--------------------------------------------------------------------------
    | Default timezone per locale
    |--------------------------------------------------------------------------
    | Used as a hint when a user has no explicit timezone preference.
    | The user's `users.timezone` column (when set) always wins.
    */
    'default_timezone_per_locale' => [
        'fr' => 'Africa/Douala',   // CAT
        'en' => 'UTC',
        'pt' => 'Africa/Luanda',
        'es' => 'America/Mexico_City',
        'ar' => 'Africa/Cairo',
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency formatting metadata
    |--------------------------------------------------------------------------
    | Each entry: code => [locale_for_format, decimals, symbol_position].
    | Frontend `formatPrice()` should use the `Intl.NumberFormat`-equivalent
    | of `locale_for_format` for consistent rendering.
    */
    'currencies' => [
        'XAF' => ['locale' => 'fr_CM', 'decimals' => 0, 'symbol' => 'FCFA'],
        'XOF' => ['locale' => 'fr_SN', 'decimals' => 0, 'symbol' => 'FCFA'],
        'NGN' => ['locale' => 'en_NG', 'decimals' => 2, 'symbol' => '₦'],
        'GHS' => ['locale' => 'en_GH', 'decimals' => 2, 'symbol' => 'GH₵'],
        'KES' => ['locale' => 'en_KE', 'decimals' => 2, 'symbol' => 'KSh'],
        'TZS' => ['locale' => 'sw_TZ', 'decimals' => 0, 'symbol' => 'TSh'],
        'UGX' => ['locale' => 'en_UG', 'decimals' => 0, 'symbol' => 'USh'],
        'ZAR' => ['locale' => 'en_ZA', 'decimals' => 2, 'symbol' => 'R'],
        'MAD' => ['locale' => 'fr_MA', 'decimals' => 2, 'symbol' => 'MAD'],
        'EGP' => ['locale' => 'ar_EG', 'decimals' => 2, 'symbol' => 'E£'],
        'EUR' => ['locale' => 'fr_FR', 'decimals' => 2, 'symbol' => '€'],
        'GBP' => ['locale' => 'en_GB', 'decimals' => 2, 'symbol' => '£'],
        'CHF' => ['locale' => 'fr_CH', 'decimals' => 2, 'symbol' => 'CHF'],
        'USD' => ['locale' => 'en_US', 'decimals' => 2, 'symbol' => '$'],
        'CAD' => ['locale' => 'en_CA', 'decimals' => 2, 'symbol' => 'CA$'],
        'BRL' => ['locale' => 'pt_BR', 'decimals' => 2, 'symbol' => 'R$'],
        'AED' => ['locale' => 'ar_AE', 'decimals' => 2, 'symbol' => 'AED'],
        'INR' => ['locale' => 'en_IN', 'decimals' => 2, 'symbol' => '₹'],
        'JPY' => ['locale' => 'ja_JP', 'decimals' => 0, 'symbol' => '¥'],
    ],

];
