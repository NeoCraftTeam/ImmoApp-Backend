<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
 *
 * AUTH-5 — Token family tracking:
 *  - Each token chain shares a `family_id` UUID (persisted on the token row).
 *  - On rotation: the old token is soft-revoked (`revoked_at = now()`) instead
 *    of hard-deleted, so that a later reuse of the revoked token can be detected.
 *  - Compromise detection: if `rotateForUser()` finds no *active* token matching
 *    the revoke pattern, but finds revoked tokens from the same family, it means
 *    a previously rotated (and stolen) token was reused — OWASP RTR compromise.
 *    → All sessions for that user are immediately revoked and an alert is logged.
 */
final class TokenService
{
    /**
     * Create a new Sanctum token for the given user.
     *
     * @param  string  $suffix  Descriptive suffix appended to the prefix (e.g. "token", "clerk", "auth").
     * @param  string|null  $prefixOverride  Force a specific prefix instead of deriving from the user's role.
     * @param  string|null  $familyId  Existing family UUID to propagate (null = start a new family).
     */
    public function createForUser(User $user, string $suffix, ?string $prefixOverride = null, ?string $familyId = null): NewAccessToken
    {
        $prefix = $prefixOverride ?? $user->sanctumSessionPrefix();
        $roleAbility = 'role:'.$user->role->value;

        $expirationMinutes = (int) config('sanctum.expiration', 43200);
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;

        $newToken = $user->createToken(
            "{$prefix}_{$suffix}_".now()->timestamp,
            [$roleAbility, 'api:access'],
            $expiresAt,
        );

        $newToken->accessToken->forceFill([
            'family_id' => $familyId ?? (string) Str::uuid(),
        ])->save();

        return $newToken;
    }

    /**
     * Soft-revoke tokens matching the given pattern, then create a fresh one.
     *
     * Implements OWASP Refresh Token Rotation (RTR) with family-compromise detection:
     * if the active token for this session is gone but a revoked ancestor still exists
     * in the DB, it means the revoked token was reused — all sessions are nuked.
     *
     * @param  string  $suffix  Descriptive suffix for the new token name.
     * @param  string|null  $revokePattern  SQL LIKE pattern — `null` means skip revocation.
     * @param  string|null  $prefixOverride  Force a specific prefix instead of deriving from the user's role.
     */
    public function rotateForUser(User $user, string $suffix, ?string $revokePattern = null, ?string $prefixOverride = null): NewAccessToken
    {
        $familyId = null;

        if ($revokePattern !== null) {
            $activeTokens = $user->tokens()
                ->where('name', 'like', $revokePattern)
                ->whereNull('revoked_at')
                ->get();

            $familyId = $activeTokens->first()?->family_id;

            if ($activeTokens->isEmpty()) {
                $revokedAncestor = $user->tokens()
                    ->where('name', 'like', $revokePattern)
                    ->whereNotNull('revoked_at')
                    ->whereNotNull('family_id')
                    ->exists();

                if ($revokedAncestor) {
                    Log::alert('auth.token_family.compromise_detected', [
                        'user_id' => $user->id,
                        'pattern' => $revokePattern,
                    ]);

                    $user->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

                    return $this->createForUser($user, $suffix, $prefixOverride);
                }
            }

            $user->tokens()
                ->where('name', 'like', $revokePattern)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        return $this->createForUser($user, $suffix, $prefixOverride, $familyId);
    }
}
