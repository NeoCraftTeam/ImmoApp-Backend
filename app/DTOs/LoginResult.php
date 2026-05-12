<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/**
 * Domain result returned by LoginService::authenticate().
 */
final readonly class LoginResult
{
    public function __construct(
        public User $user,
        public NewAccessToken $token,
    ) {}
}
