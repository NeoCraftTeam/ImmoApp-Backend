<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;
    use MassPrunable;

    /**
     * Prune tokens unused for more than 90 days.
     */
    public function prunable(): Builder
    {
        return static::where('last_used_at', '<', now()->subDays(90))
            ->orWhere(function (Builder $query): void {
                $query->whereNull('last_used_at')
                    ->where('created_at', '<', now()->subDays(90));
            });
    }
}
