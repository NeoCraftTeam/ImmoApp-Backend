<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;
use App\Services\Auth\MfaChallengeService;

/**
 * A login that passed the first factor and is waiting on the second.
 *
 * The plaintext {@see $token} is handed to the client exactly once (only the
 * SHA-256 digest is stored server-side) and is the sole credential accepted by
 * `POST /api/v1/auth/mfa/challenge`.
 */
final readonly class MfaChallenge
{
    /**
     * @param  string  $source  Which login surface created it: `password`,
     *                          `oauth`, `oauth_link` or `clerk`. Decides how the
     *                          Sanctum token is issued once the challenge passes.
     * @param  string|null  $provider  OAuth provider, for the `oauth` source.
     * @param  bool  $stateful  The originating request had a session, so the web
     *                          guard must be logged in on completion too.
     * @param  int  $attempts  Wrong codes submitted so far.
     * @param  string  $throttleKey  RateLimiter key shared with the login attempt
     *                               that created the challenge, so guessing codes
     *                               spends the very same per-IP budget as guessing
     *                               passwords instead of resetting it.
     * @param  string|null  $token  Plaintext token — only set by
     *                              {@see MfaChallengeService::issue()}.
     */
    public function __construct(
        public User $user,
        public string $source,
        public ?string $loginContext = null,
        public ?string $provider = null,
        public bool $isNewUser = false,
        public bool $stateful = false,
        public int $attempts = 0,
        public string $throttleKey = '',
        public ?string $token = null,
    ) {}
}
