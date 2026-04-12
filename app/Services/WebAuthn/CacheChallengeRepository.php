<?php

declare(strict_types=1);

namespace App\Services\WebAuthn;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Support\Facades\Cache;
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
 * - **Unauthenticated** (passkey login): keyed by a challenge token sent via
 *   `X-WebAuthn-Token` header. The frontend must store the token from the
 *   options response and send it back on the verification request.
 * - **Fallback**: session ID (Filament panel with active session)
 */
final readonly class CacheChallengeRepository implements WebAuthnChallengeRepository
{
    public function __construct(private ConfigContract $config) {}

    public function store(AttestationCreation|AssertionCreation $ceremony, Challenge $challenge): void
    {
        $identifier = $this->resolveIdentifier();

        // For unauthenticated login flows, generate a token and store it
        // so the controller can include it in the response.
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
    }

    public function pull(AttestationValidation|AssertionValidation $ceremony): ?Challenge
    {
        $identifier = $this->resolveIdentifier();

        if (!$identifier) {
            $identifier = session()->getId();
        }

        $key = $this->cacheKey($identifier);

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

        // Unauthenticated — use the challenge token from header
        $token = request()->header('X-WebAuthn-Token');
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
