<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contract for the ad recommendation engine.
 *
 * Implementations return a ranked list of ad recommendations
 * personalised to the given user (or generic for guests).
 */
interface RecommendationEngineInterface
{
    /**
     * Return an ordered array of recommended ads for the user.
     *
     * @return array{ads: Collection<int, Ad>, meta: array<string, mixed>}
     */
    public function recommend(?User $user): array;
}
