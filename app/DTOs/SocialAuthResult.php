<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;

/**
 * Result of SocialAuthService::findOrCreateUser(): the resolved user and
 * whether it was just created, plus the optional cross-provider link handshake
 * (when an account already exists for the OAuth email and must confirm linking
 * before the provider is attached).
 */
final readonly class SocialAuthResult
{
    public function __construct(
        public User $user,
        public bool $isNew,
        public bool $requiresLinkConfirmation = false,
        public ?string $linkingToken = null,
    ) {}
}
