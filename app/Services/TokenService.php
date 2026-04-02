<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/**
 * Centralised Sanctum token creation for role-scoped sessions.
 *
 * Every token carries two abilities:
 *  - `role:<role>` — mirrors the user's current role enum
 *  - `api:access`  — generic API gate
 *
 * Token names are prefixed with `owner_` or `client_` depending on the
 * user's role, so middleware can enforce session isolation.
 */
final class TokenService
{
    /**
     * Create a new Sanctum token for the given user.
     *
     * @param  string  $suffix  Descriptive suffix appended to the prefix (e.g. "token", "clerk", "auth").
     * @param  string|null  $prefixOverride  Force a specific prefix instead of deriving from the user's role.
     */
    public function createForUser(User $user, string $suffix, ?string $prefixOverride = null): NewAccessToken
    {
        $prefix = $prefixOverride ?? ($user->isAgent() || $user->isAdmin() ? 'owner' : 'client');
        $roleAbility = 'role:'.$user->role->value;

        return $user->createToken(
            "{$prefix}_{$suffix}_".now()->timestamp,
            [$roleAbility, 'api:access'],
            now()->addDay()
        );
    }

    /**
     * Revoke tokens matching the given pattern, then create a fresh one.
     *
     * @param  string  $suffix  Descriptive suffix for the new token name.
     * @param  string|null  $revokePattern  SQL LIKE pattern — `null` means skip revocation.
     * @param  string|null  $prefixOverride  Force a specific prefix instead of deriving from the user's role.
     */
    public function rotateForUser(User $user, string $suffix, ?string $revokePattern = null, ?string $prefixOverride = null): NewAccessToken
    {
        if ($revokePattern !== null) {
            $user->tokens()->where('name', 'like', $revokePattern)->delete();
        }

        return $this->createForUser($user, $suffix, $prefixOverride);
    }
}
