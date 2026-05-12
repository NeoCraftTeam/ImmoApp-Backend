<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/**
 * Domain result returned by RegistrationService::register().
 *
 * Decouples the service from HTTP concerns — the controller
 * maps this into a JsonResponse.
 */
final readonly class RegistrationResult
{
    public function __construct(
        public User $user,
        public NewAccessToken $token,
        public bool $emailVerificationRequired,
    ) {}
}
