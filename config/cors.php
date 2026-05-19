<?php

/*
 * In production, keep only the production origins listed in `allowed_origins`.
 * In development, additional local IPs and the Herd `.test` domain are added.
 * The `allowed_origins_patterns` entry below covers preview-deploy subdomains
 * (e.g. staging.keyhome.neocraft.dev) — it intentionally limits to known
 * prefixes rather than matching any arbitrary subdomain.
 */

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

    'paths' => ['api/*', 'auth/*', 'sanctum/csrf-cookie', 'broadcasting/*', 'login', 'register', 'storage/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter([
        ...((string) env('APP_ENV') !== 'production' ? [
            'http://localhost:3000',
            'https://localhost:3000',
            'https://keyhome.test',
            'http://keyhome.test:3000',
            'https://keyhome.test:3000',
        ] : []),
        'https://api.keyhome.neocraft.dev',
        'https://keyhome.neocraft.dev',
        'https://preview.keyhome.neocraft.dev',
        'https://keyhome.app',
        'https://www.keyhome.app',
        'https://owner.keyhome.app',
        'https://panel.keyhome.app',
        'https://api.keyhome.app',
    ]),

    // Covers named preview/staging subdomains on both .neocraft.dev and .neocraft.de.
    // preview(-[a-z0-9-]+)? matches both bare 'preview' and 'preview-abc123'.
    'allowed_origins_patterns' => [
        '/^https:\/\/(staging|preprod|preview(-[a-z0-9-]+)?)\.keyhome\.neocraft\.dev$/',
        '/^https:\/\/(staging|preprod|preview(-[a-z0-9-]+)?)\.keyhome\.neocraft\.de$/',
    ],

    'allowed_headers' => ['Accept', 'Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-Token', 'X-Inertia', 'X-WebAuthn-Token', 'X-Socket-Id', 'X-Request-ID', 'X-Correlation-ID'],

    'exposed_headers' => ['X-WebAuthn-Token', 'X-Request-ID', 'X-Correlation-ID'],

    'max_age' => 86400,  // 24 heures (améliore les performances)

    'supports_credentials' => true,

];
