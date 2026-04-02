<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Configure trusted reverse proxies for correct IP detection in rate limiting
    | and security logging. In production, set TRUSTED_PROXIES to your load
    | balancer or CDN IP range (e.g., Cloudflare: "173.245.48.0/20,...")
    |
    | Must use config() not env() directly to work with config:cache.
    |
    */
    'trusted' => env('TRUSTED_PROXIES', '127.0.0.1'),
    'headers' => env('TRUSTED_PROXY_HEADERS', 'FORWARDED,X_FORWARDED_FOR,X_FORWARDED_HOST,X_FORWARDED_PORT,X_FORWARDED_PROTO'),
];
