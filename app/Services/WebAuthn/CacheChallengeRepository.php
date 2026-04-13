<?php

declare(strict_types=1);

namespace App\Services\WebAuthn;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Challenge\Challenge;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;

/**
 * Cache-based challenge repository for WebAuthn.
 *
 * The default SessionChallengeRepository breaks when SESSION_DRIVER=cookie
 * because the challenge data pushes the cookie beyond the 4 KB browser limit.
 *
 * This implementation stores challenges in Redis (cache) instead, using:
 * - **Authenticated user**: keyed by user ID (works for both session + API/Sanctum)
 * - **Unauthenticated** (passkey login, both API and web panel): keyed by a one-time
 *   random token. The token is set on the request attributes during `store()` so
 *   the controller can read it and embed it in the response body as `_wt` (and
 *   optionally as the `X-WebAuthn-Token` response header). The frontend must
 *   include the token in the following verify request — either via the
 *   `X-WebAuthn-Token` header or the `_wt` body field.
 *
 *   We deliberately do NOT use `session()->getId()` as the cache key. The session
 *   ID changes between the /options request and the /login request in the Filament
 *   panel because Livewire or the auth middleware calls `session()->migrate()` which
 *   rotates the session ID. Using the explicit token avoids this instability entirely.
 * - **Fallback**: session ID (registration / other authenticated flows)
 */
final readonly class CacheChallengeRepository implements WebAuthnChallengeRepository
{
    public function __construct(private ConfigContract $config) {}

    public function store(AttestationCreation|AssertionCreation $ceremony, Challenge $challenge): void
    {
        $identifier = $this->resolveIdentifier();

        // For unauthenticated login (assertion) flows, generate a one-time random
        // token and store it on the request attributes so controllers can read it
        // and embed it in the response body as `_wt` (and/or X-WebAuthn-Token header).
        // The frontend must echo the token back on the verify request.
        //
        // We deliberately avoid using session()->getId(): the session ID rotates
        // between the /options and /login requests in the Filament panel (Livewire /
        // auth middleware calls session()->migrate()), so a session-ID-keyed cache
        // entry is never found by the follow-up request.
        if (!$identifier && $ceremony instanceof AssertionCreation) {
            $rawToken = bin2hex(random_bytes(16));
            request()->attributes->set('webauthn_challenge_token', $rawToken);
            $identifier = 'token:'.$rawToken;
        }

        if (!$identifier) {
            $identifier = session()->getId();
        }

        $key = $this->cacheKey($identifier);
        $ttl = $challenge->timeout + 30;

        Cache::put($key, $challenge, $ttl);

        Log::debug('[WebAuthn] STORE', ['key' => $key, 'identifier' => $identifier, 'ttl' => $ttl]);
    }

    public function pull(AttestationValidation|AssertionValidation $ceremony): ?Challenge
    {
        $identifier = $this->resolveIdentifier();

        if (!$identifier) {
            $identifier = session()->getId();
        }

        $key = $this->cacheKey($identifier);

        Log::debug('[WebAuthn] PULL', [
            'key' => $key,
            'identifier' => $identifier,
            'header_token' => request()->header('X-WebAuthn-Token'),
            '_wt_body' => request()->input('_wt'),
            'exists' => Cache::has($key),
        ]);

        /** @var Challenge|array|null $challenge */
        $challenge = Cache::pull($key);

        if (is_array($challenge)) {
            $challenge = Challenge::fromArray($challenge);
        }

        if ($challenge?->isValid()) {
            return $challenge;
        }

        return null;
    }

    /**
     * Resolve a unique identifier for the current request.
     *
     * Priority: authenticated user ID → X-WebAuthn-Token header → null.
     */
    private function resolveIdentifier(): ?string
    {
        // Authenticated user (Sanctum or session)
        $user = request()->user();
        if ($user) {
            return 'user:'.$user->getAuthIdentifier();
        }

        // Unauthenticated — check X-WebAuthn-Token header first (API / PWA flow),
        // then the _wt body field (Filament web panel flow where the Alpine.js
        // component passes the token back as a JSON body field).
        $token = request()->header('X-WebAuthn-Token') ?: request()->input('_wt');
        if ($token) {
            return 'token:'.$token;
        }

        return null;
    }

    private function cacheKey(string $identifier): string
    {
        $prefix = $this->config->get('webauthn.challenge.key', '_webauthn');

        return "webauthn_challenge:{$prefix}:{$identifier}";
    }
}
