<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Rate Limits
    |--------------------------------------------------------------------------
    | Requests per window (unit depends on each limiter definition).
    | Tune via .env to adjust per environment without code changes.
    */
    'auth' => [
        'register' => (int) env('RL_AUTH_REGISTER', 5),       // per minute
        'login' => (int) env('RL_AUTH_LOGIN', 5),           // per minute
        'resend_verify' => (int) env('RL_AUTH_RESEND_VERIFY', 2),   // per 5 min
        'verify_email' => (int) env('RL_AUTH_VERIFY_EMAIL', 5),    // per 10 min
        'verify_otp' => (int) env('RL_AUTH_VERIFY_OTP', 5),      // per minute
        'password_reset' => (int) env('RL_AUTH_PASSWORD_RESET', 3),  // per 10 min
        'social_auth' => (int) env('RL_AUTH_SOCIAL', 10),         // per minute
        'clerk' => (int) env('RL_AUTH_CLERK', 10),          // per minute
        'clerk_otp' => (int) env('RL_AUTH_CLERK_OTP', 5),       // per minute
        'update_password' => (int) env('RL_AUTH_UPDATE_PASSWORD', 5), // per 10 min
        'general' => (int) env('RL_AUTH_GENERAL', 30),        // per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Rate Limits
    |--------------------------------------------------------------------------
    */
    'payments' => [
        'initiate' => (int) env('RL_PAYMENT_INITIATE', 5),    // per minute
        'verify' => (int) env('RL_PAYMENT_VERIFY', 30),     // per minute
        'cancel' => (int) env('RL_PAYMENT_CANCEL', 10),     // per minute
        'webhook' => (int) env('RL_PAYMENT_WEBHOOK', 120),   // per minute
        'history' => (int) env('RL_PAYMENT_HISTORY', 60),    // per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Viewings — tentative reservations (client POST)
    |--------------------------------------------------------------------------
    |
    | Per authenticated user / minute. Keeps abuse bounded while allowing
    | legitimate retries (validation errors, slot contention, flaky networks).
    |
    */
    'viewings' => [
        'reserve' => (int) env('RL_VIEWINGS_RESERVE', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Search — natural-language parser (POST /ads/search/parse)
    |--------------------------------------------------------------------------
    |
    | The endpoint forwards each query to a paid LLM provider. Without a daily
    | ceiling a single IP could burn through Groq+OpenAI credits at full speed.
    | The route stacks the per-minute and per-day limiters; the global hourly
    | ceiling caps cluster-wide cost spikes (OWASP LLM10:2025).
    |
    */
    'ai_search' => [
        'parse_minute' => (int) env('RL_AI_SEARCH_PARSE_MINUTE', 30),
        'parse_day' => (int) env('RL_AI_SEARCH_PARSE_DAY', 200),
        'parse_hourly_global' => (int) env('RL_AI_SEARCH_PARSE_HOURLY_GLOBAL', 10000),
    ],

];
