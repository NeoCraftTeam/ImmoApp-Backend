<?php

/**
 * Feature flags configuration.
 *
 * Toggle features without redeployment by changing env vars.
 * Admins can also toggle features via the Filament admin panel.
 *
 * Each flag is a boolean: true = enabled, false = disabled.
 * Flags can be overridden at runtime via the `settings` table.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Each key represents a feature that can be toggled on/off.
    | The env() fallback provides a safe default for each flag.
    |
    */

    'natural_search' => env('FEATURE_NATURAL_SEARCH', true),
    'ai_description' => env('FEATURE_AI_DESCRIPTION', true),
    'keyscore' => env('FEATURE_KEYSCORE', true),
    'rent_estimator' => env('FEATURE_RENT_ESTIMATOR', true),
    'price_heatmap' => env('FEATURE_PRICE_HEATMAP', true),
    'three_d_tours' => env('FEATURE_3D_TOURS', true),
    'search_alerts' => env('FEATURE_SEARCH_ALERTS', true),
    'lease_contracts' => env('FEATURE_LEASE_CONTRACTS', true),
    'viewing_reservations' => env('FEATURE_VIEWING_RESERVATIONS', true),
    'surveys' => env('FEATURE_SURVEYS', true),
    'newsletter' => env('FEATURE_NEWSLETTER', true),
    'recommendations' => env('FEATURE_RECOMMENDATIONS', true),
    'social_auth' => env('FEATURE_SOCIAL_AUTH', true),
    'push_notifications' => env('FEATURE_PUSH_NOTIFICATIONS', true),
    'review_verification' => env('FEATURE_REVIEW_VERIFICATION', true),
    'gdpr_export' => env('FEATURE_GDPR_EXPORT', true),

    // A/B tests
    'ab_search_geolocation' => env('FEATURE_AB_SEARCH_GEOLOCATION', false),

];
