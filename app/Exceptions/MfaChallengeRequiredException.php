<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\DTOs\MfaChallenge;
use App\Services\Auth\LoginService;
use RuntimeException;

/**
 * Thrown when the first factor succeeded but the account requires a second one.
 *
 * Only {@see LoginService::authenticate()} throws it — the
 * OAuth and Clerk controllers wrap their whole flow in `catch (Exception)` and
 * would swallow it, so those surfaces *return* the challenge response instead.
 */
final class MfaChallengeRequiredException extends RuntimeException
{
    public function __construct(public readonly MfaChallenge $challenge)
    {
        parent::__construct('Vérification en deux étapes requise.');
    }
}
