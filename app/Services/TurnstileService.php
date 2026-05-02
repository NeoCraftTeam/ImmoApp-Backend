<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile (free CAPTCHA) verification.
 *
 * Verifies a client-supplied turnstile token by POSTing it to the Cloudflare
 * siteverify endpoint along with the secret key. Returns `true` only when
 * Cloudflare confirms the token came from a real human on the configured
 * site.
 *
 * Ops:
 *  - `TURNSTILE_SITE_KEY`  — public, safe to expose in frontend env.
 *  - `TURNSTILE_SECRET_KEY` — server-only.
 * When either is empty, the service silently returns `true` so non-prod
 * environments don't require a Cloudflare account to test the auth flow.
 */
final readonly class TurnstileService
{
    private const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function isConfigured(): bool
    {
        $secret = (string) config('services.turnstile.secret_key', '');

        return $secret !== '';
    }

    /**
     * Verify a Turnstile token against Cloudflare's siteverify endpoint.
     *
     * Returns `true` when the token is valid OR when Turnstile is not
     * configured (development/testing fallback).
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (!$this->isConfigured()) {
            // Turnstile is optional — when not configured, fail open so dev
            // environments aren't blocked. Production MUST set the secret.
            return true;
        }

        if ($token === null || trim($token) === '') {
            return false;
        }

        $secret = (string) config('services.turnstile.secret_key', '');

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));

            if ($response->failed()) {
                Log::warning('Turnstile siteverify HTTP error', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            $body = (array) $response->json();
            $success = (bool) ($body['success'] ?? false);

            if (!$success) {
                Log::info('Turnstile token rejected', [
                    'error_codes' => $body['error-codes'] ?? [],
                ]);
            }

            return $success;
        } catch (\Throwable $e) {
            Log::warning('Turnstile verify exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
