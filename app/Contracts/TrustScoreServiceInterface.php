<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\TrustScoreTier;
use App\Models\User;

/**
 * Contract for the user trust-score computation engine.
 *
 * Implementations compute a bidirectional trust score (0–100)
 * for both tenants and landlords.
 */
interface TrustScoreServiceInterface
{
    /**
     * Compute and persist the trust score for a user.
     *
     * @return array{score: int, tier: TrustScoreTier, breakdown: array<string, mixed>, label: string}
     */
    public function compute(User $user): array;

    /**
     * Get cached score or compute fresh.
     *
     * @return array{score: int, tier: TrustScoreTier, breakdown: array<string, mixed>, label: string}
     */
    public function getOrCompute(User $user): array;

    /**
     * Invalidate cached score (call after relevant data changes).
     */
    public function invalidate(User $user): void;
}
