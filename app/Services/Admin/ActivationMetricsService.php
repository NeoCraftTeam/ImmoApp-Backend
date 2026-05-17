<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\AdInteraction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Computes user-activation metrics: profile completion, time-to-first-action,
 * first publication rate, and first search rate.
 */
final class ActivationMetricsService
{
    private const int CACHE_TTL = 900;

    /**
     * @return array{
     *     profile_completion_rate: float,
     *     avg_time_to_first_action: float,
     *     first_publication_rate: float,
     *     first_search_rate: float
     * }
     */
    public function get(): array
    {
        return Cache::remember('admin_activation', self::CACHE_TTL, function () {
            $totalUsers = User::count();
            $completedProfiles = User::whereNotNull('onboarding_completed_at')->count();
            $profileCompletionRate = $totalUsers > 0 ? round(($completedProfiles / $totalUsers) * 100, 1) : 0;

            $avgResult = DB::selectOne('
                SELECT AVG(EXTRACT(EPOCH FROM (first_action - u.created_at)) / 3600) as avg_hours
                FROM users u
                INNER JOIN LATERAL (
                    SELECT MIN(created_at) as first_action
                    FROM ad_interactions
                    WHERE user_id = u.id
                ) ai ON ai.first_action IS NOT NULL
                WHERE u.created_at >= ?
            ', [now()->subMonths(3)]);
            $avgTimeToFirstAction = $avgResult ? (float) $avgResult->avg_hours : 0;

            $totalOwners = User::where('role', UserRole::AGENT)->count();
            $ownersWithAds = User::where('role', UserRole::AGENT)
                ->whereHas('ads')
                ->count();
            $firstPublicationRate = $totalOwners > 0 ? round(($ownersWithAds / $totalOwners) * 100, 1) : 0;

            $totalCustomers = User::where('role', UserRole::CUSTOMER)->count();
            $customersWithSearch = User::where('role', UserRole::CUSTOMER)
                ->whereHas('adInteractions', fn ($q) => $q->where('type', AdInteraction::TYPE_SEARCH))
                ->count();
            $firstSearchRate = $totalCustomers > 0 ? round(($customersWithSearch / $totalCustomers) * 100, 1) : 0;

            return [
                'profile_completion_rate' => $profileCompletionRate,
                'avg_time_to_first_action' => round($avgTimeToFirstAction, 1),
                'first_publication_rate' => $firstPublicationRate,
                'first_search_rate' => $firstSearchRate,
            ];
        });
    }
}
