<?php

declare(strict_types=1);

namespace App\Support;

/**
 * OpenRouteService cloud expects {@code Authorization: Bearer <token>}.
 * Legacy keys / self-hosted instances may use the raw token only — set
 * {@code ORS_AUTHORIZATION_RAW=true} to skip the Bearer prefix.
 */
final class OpenRouteServiceAuth
{
    public static function authorizationHeader(string $apiKey): string
    {
        $key = trim($apiKey);

        if ($key === '') {
            return '';
        }

        if (filter_var(config('services.ors.authorization_raw', false), FILTER_VALIDATE_BOOL)) {
            return $key;
        }

        if (preg_match('/^Bearer\s+/i', $key) === 1) {
            return $key;
        }

        return 'Bearer '.$key;
    }
}
