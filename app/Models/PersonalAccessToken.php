<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * @property string|null $family_id
 * @property Carbon|null $revoked_at
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;
    use MassPrunable;

    /**
     * AUTH-5 : Expose family_id and revoked_at for token family tracking.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'last_used_at',
        'family_id',
        'revoked_at',
    ];

    /**
     * AUTH-5 : Exclude soft-revoked tokens from Sanctum authentication.
     *
     * Sanctum calls this static method to resolve a Bearer token. By adding
     * the `revoked_at is null` guard here, revoked tokens are treated as
     * non-existent — preventing their reuse even before the expiry date.
     */
    #[\Override]
    public static function findToken(mixed $token): ?static
    {
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (!is_string($token)) {
            return null;
        }

        if (!str_contains($token, '|')) {
            /** @phpstan-ignore return.type */
            return static::where('token', hash('sha256', $token))
                ->whereNull('revoked_at')
                ->first();
        }

        [$id, $token] = explode('|', $token, 2);

        /** @var static|null $instance */
        $instance = static::find($id);

        if ($instance && hash_equals($instance->token, hash('sha256', $token)) && $instance->revoked_at === null) {
            return $instance;
        }

        return null;
    }

    /**
     * Prune tokens unused for more than 90 days, or revoked for more than 30 days.
     *
     * Revoked tokens are kept briefly so the compromise-detection logic in
     * TokenService can recognise a revoked-token reuse within its grace window.
     */
    public function prunable(): Builder
    {
        return static::where(function (Builder $query): void {
            $query->where('last_used_at', '<', now()->subDays(90))
                ->orWhere(function (Builder $inner): void {
                    $inner->whereNull('last_used_at')
                        ->where('created_at', '<', now()->subDays(90));
                });
        })->orWhere('revoked_at', '<', now()->subDays(30));
    }
}
