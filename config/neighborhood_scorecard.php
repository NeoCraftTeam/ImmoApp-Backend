<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Overpass API endpoint
    |--------------------------------------------------------------------------
    |
    | Public Overpass instance by default. Override for a self-hosted mirror
    | in production if you need higher quotas or lower latency.
    |
    */

    'overpass_url' => env(
        'NEIGHBORHOOD_SCORECARD_OVERPASS_URL',
        'https://overpass-api.de/api/interpreter',
    ),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Successful OSM responses are cached for a long TTL (POI data changes slowly).
    | Failed / degraded responses use a short TTL so transient outages recover quickly.
    |
    */

    'cache_ttl_ok_seconds' => (int) env('NEIGHBORHOOD_SCORECARD_CACHE_TTL', 604_800),

    'cache_ttl_degraded_seconds' => (int) env('NEIGHBORHOOD_SCORECARD_CACHE_TTL_DEGRADED', 300),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    */

    'http_timeout_seconds' => (int) env('NEIGHBORHOOD_SCORECARD_HTTP_TIMEOUT', 15),

    'http_retry_times' => (int) env('NEIGHBORHOOD_SCORECARD_HTTP_RETRY_TIMES', 2),

    'http_retry_delay_ms' => (int) env('NEIGHBORHOOD_SCORECARD_HTTP_RETRY_DELAY_MS', 1_500),

    /*
    |--------------------------------------------------------------------------
    | OpenRouteService (optional — real walking distances)
    |--------------------------------------------------------------------------
    |
    | When set, one foot-walking matrix call computes street-network distances
    | from the ad to the nearest OSM node per category. Falls back to
    | orthodromic distance if the request fails or the key is empty.
    |
    | https://openrouteservice.org/dev/#/api-docs/v2/Matrix/post_{profile}_matrix
    |
    */

    'openrouteservice_api_key' => env('NEIGHBORHOOD_SCORECARD_ORS_API_KEY', ''),

    'openrouteservice_matrix_url' => env(
        'NEIGHBORHOOD_SCORECARD_ORS_MATRIX_URL',
        'https://api.openrouteservice.org/v2/matrix/foot-walking',
    ),

    'openrouteservice_timeout_seconds' => (int) env('NEIGHBORHOOD_SCORECARD_ORS_TIMEOUT', 12),
];
