<?php

$turnstileUsesProductionKeysInLocal = filter_var(
    env('TURNSTILE_USE_PRODUCTION_KEYS', false),
    FILTER_VALIDATE_BOOLEAN
);

$isTurnstileAppLocal = env('APP_ENV') === 'local';

if ($isTurnstileAppLocal && !$turnstileUsesProductionKeysInLocal) {
    $turnstileSiteKey = '1x00000000000000000000AA';
    $turnstileSecretKey = '1x0000000000000000000000000000000AA';
} else {
    $turnstileSiteKey = filled(env('TURNSTILE_SITE_KEY'))
        ? (string) env('TURNSTILE_SITE_KEY')
        : '';
    $turnstileSecretKey = filled(env('TURNSTILE_SECRET_KEY'))
        ? (string) env('TURNSTILE_SECRET_KEY')
        : '';
}

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile (CAPTCHA)
    |--------------------------------------------------------------------------
    | Free, privacy-respecting bot mitigation. Used on /login and /register.
    | When `secret_key` is empty (non-local), `TurnstileService` fails open.
    |
    | When `APP_ENV` is `local`, dummy Cloudflare keys are used **even if**
    | `TURNSTILE_*` is set in `.env`, so http://localhost works without widget error 110200.
    | Set `TURNSTILE_USE_PRODUCTION_KEYS=true` to test real keys on local (hostnames must include localhost).
    | @see https://developers.cloudflare.com/turnstile/troubleshooting/testing/
    */
    'turnstile' => [
        'site_key' => $turnstileSiteKey,
        'secret_key' => $turnstileSecretKey,
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Providers (Laravel Socialite)
    |--------------------------------------------------------------------------
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/api/v1/auth/oauth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/api/v1/auth/oauth/facebook/callback'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI', '/api/v1/auth/oauth/apple/callback'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    'together' => [
        'api_key' => env('TOGETHER_API_KEY'),
        'model' => env('TOGETHER_MODEL', 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo'),
    ],

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
    ],

    'ai' => [
        // Which provider to use: openai | groq | gemini
        'provider' => env('AI_PROVIDER', 'openai'),
    ],

    'ai_search' => [
        // Ordered, comma-separated list of LLM providers to try for natural language search.
        // First provider with a valid API key and open circuit will be used.
        // Supported: groq, openai, gemini, together, mistral
        'providers' => env('AI_SEARCH_PROVIDERS', 'groq,openai,gemini'),
    ],

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    ],

    'ors' => [
        'key' => env('ORS_API_KEY'),
        /** When true, send the API key as {@code Authorization} value without Bearer prefix (self-hosted ORS). */
        'authorization_raw' => filter_var(env('ORS_AUTHORIZATION_RAW', false), FILTER_VALIDATE_BOOL),
    ],

    'health' => [
        'token' => env('HEALTH_CHECK_TOKEN'),
    ],

    'mapbox' => [
        'token' => env('MAPBOX_TOKEN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe (card payments via Laravel Cashier)
    |--------------------------------------------------------------------------
    | Stripe handles every PaymentMethod whose `gateway()` returns
    | PaymentGateway::Stripe — currently `card` only. Mobile money methods
    | continue to flow through Flutterwave.
    |
    | Stripe does NOT support XAF/XOF as a settlement currency, so KeyHome
    | bills Stripe in EUR using the official CFA peg : 1 EUR = 655.957 XAF.
    | The original XAF amount is kept canonical inside `payments.amount`,
    | the EUR equivalent is stored in `payments.gateway_response.eur_amount`
    | for audit. See `App\Services\Payment\StripePaymentService::convertXafToEurCents`.
    |
    | A single `STRIPE_WEBHOOK_SECRET` is used for both test and live —
    | rotate the value in `.env` when switching environments.
    */
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'webhook_tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        // Settlement currency for every Stripe charge. Override only if you
        // know what you're doing — XAF/XOF are NOT supported by Stripe.
        'currency' => env('STRIPE_CURRENCY', 'eur'),
        // Pinned XAF→EUR conversion rate (CFA franc BEAC official peg).
        // Hard-coded, NOT fetched from /api/exchange-rates, so receipts and
        // refunds always reconcile to the same euro amount.
        'xaf_to_eur_rate' => 655.957,
    ],

];
