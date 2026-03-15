<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS Origins for Media / Tour Proxies
    |--------------------------------------------------------------------------
    |
    | The tour-image and media-proxy endpoints stream assets to the browser.
    | Restrict allowed origins to known frontend domains for security.
    | Use '*' only when Pannellum or third-party embeds require wildcard.
    |
    | When null or empty, falls back to config('cors.allowed_origins').
    | Set PROXY_CORS_ORIGINS=* to allow any origin (not recommended in production).
    |
    */

    'allowed_origins' => env('PROXY_CORS_ORIGINS') === '*'
        ? ['*']
        : (env('PROXY_CORS_ORIGINS')
            ? array_map('trim', explode(',', (string) env('PROXY_CORS_ORIGINS')))
            : config('cors.allowed_origins', [])),

];
