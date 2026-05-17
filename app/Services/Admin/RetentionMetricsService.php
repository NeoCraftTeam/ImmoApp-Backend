<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\AdInteraction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Computes user-retention metrics (DAU/WAU/MAU, stickiness, cohort analysis).
 */
final class RetentionMetricsService
{
    private const int CACHE_TTL_SHORT = 300;

    private const int CACHE_TTL_LONG = 900;

    /** Weeks checked when building retention columns. */
    private const array RETENTION_WEEKS = [1, 2, 4, 8, 12];

    /**
     * @return array{
     *     dau: int,
     *     wau: int,
     *     mau: int,
     *     stickiness: float,
     *     return_rate_7d: float,
     *     active_landlords: int,
     *     inactive_landlords: int
     * }
     */
    public function getRetentionMetrics(): array
    {
        return Cache::remember('admin_retention', self::CACHE_TTL_SHORT, function () {
            $dau = AdInteraction::where('created_at', '>=', now()->startOfDay())
                ->distinct('user_id')
                ->count('user_id');

            $wau = AdInteraction::where('created_at', '>=', now()->startOfWeek())
                ->distinct('user_id')
                ->count('user_id');

            $mau = AdInteraction::where('created_at', '>=', now()->startOfMonth())
                ->distinct('user_id')
                ->count('user_id');

            $stickiness = $mau > 0 ? round(($dau / $mau) * 100, 1) : 0;

            $weekAgoUsers = AdInteraction::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
                ->distinct('user_id')
                ->pluck('user_id');

            $returnedUsers = $weekAgoUsers->isNotEmpty()
                ? AdInteraction::where('created_at', '>=', now()->subDays(7))
                    ->whereIn('user_id', $weekAgoUsers)
                    ->distinct('user_id')
                    ->count('user_id')
                : 0;

            $returnRate = $weekAgoUsers->count() > 0 ? round(($returnedUsers / $weekAgoUsers->count()) * 100, 1) : 0;

            $activeLandlords = User::where('role', UserRole::AGENT)
                ->whereHas('ads', fn ($q) => $q->where('updated_at', '>=', now()->subDays(30)))
                ->count();

            $totalLandlords = User::where('role', UserRole::AGENT)->count();
            $inactiveLandlords = $totalLandlords - $activeLandlords;

            return [
                'dau' => $dau,
                'wau' => $wau,
                'mau' => $mau,
                'stickiness' => $stickiness,
                'return_rate_7d' => $returnRate,
                'active_landlords' => $activeLandlords,
                'inactive_landlords' => max(0, $inactiveLandlords),
            ];
        });
    }

    /**
     * Cohort retention analysis.
     *
     * Replaces the previous nested for/foreach that fired up to 72 individual
     * AdInteraction queries (one per cohort × week combination). Instead we:
     *   1. Fetch every cohort user in a single query, keyed by cohort week.
     *   2. Fetch all relevant interactions in a single query.
     *   3. Pivot entirely in PHP.
     *
     * @return array<int, array{week: string, cohort_size: int, retention: array<int, float>}>
     */
    public function getCohortRetention(int $weeks = 12): array
    {
        // @phpstan-ignore-next-line (Larastan: unresolvable type in Cache::remember closure)
        return Cache::remember("admin_cohort_{$weeks}", self::CACHE_TTL_LONG, function () use ($weeks) {
            $cohortStart = now()->subWeeks($weeks - 1)->startOfWeek();

            // 1. All users who registered within the cohort window, with their cohort week label.
            $allUsers = DB::table('users')
                ->where('created_at', '>=', $cohortStart)
                ->whereNull('deleted_at')
                ->select('id', 'created_at')
                ->get();

            // Map: cohort_week_label -> [user_id, ...]
            /** @var array<string, array<int, int>> $cohortMap */
            $cohortMap = [];
            // Map: user_id -> cohort_week Carbon start
            /** @var array<int, Carbon> $userCohortWeek */
            $userCohortWeek = [];

            foreach ($allUsers as $user) {
                $weekStart = Carbon::parse($user->created_at)->startOfWeek();
                $label = $weekStart->format('d/m');
                $cohortMap[$label]['start'] = $weekStart;
                $cohortMap[$label]['users'][] = $user->id;
                $userCohortWeek[$user->id] = $weekStart;
            }

            if (empty($cohortMap)) {
                // Return empty shells for each week slot.
                $result = [];
                for ($i = $weeks - 1; $i >= 0; $i--) {
                    $result[] = [
                        'week' => now()->subWeeks($i)->startOfWeek()->format('d/m'),
                        'cohort_size' => 0,
                        'retention' => [],
                    ];
                }

                return $result;
            }

            $allUserIds = array_keys($userCohortWeek);

            // 2. Fetch all interactions for those users in one query.
            $interactions = DB::table('ad_interactions')
                ->whereIn('user_id', $allUserIds)
                ->where('created_at', '>=', $cohortStart)
                ->select('user_id', 'created_at')
                ->get();

            // Map: user_id -> [timestamps]
            /** @var array<int, list<string>> $interactionsByUser */
            $interactionsByUser = [];
            foreach ($interactions as $interaction) {
                $interactionsByUser[$interaction->user_id][] = $interaction->created_at;
            }

            // 3. Build the cohort grid week-by-week, pivoting in PHP.
            $result = [];

            for ($i = $weeks - 1; $i >= 0; $i--) {
                $weekStart = now()->subWeeks($i)->startOfWeek();
                $label = $weekStart->format('d/m');

                $cohortUserIds = $cohortMap[$label]['users'] ?? [];
                $cohortSize = count($cohortUserIds);

                if ($cohortSize === 0) {
                    $result[] = [
                        'week' => $label,
                        'cohort_size' => 0,
                        'retention' => [],
                    ];

                    continue;
                }

                $retention = [];

                foreach (self::RETENTION_WEEKS as $w) {
                    $checkStart = $weekStart->copy()->addWeeks($w);
                    $checkEnd = $checkStart->copy()->endOfWeek();

                    if ($checkStart->isFuture()) {
                        break;
                    }

                    $activeCount = 0;
                    foreach ($cohortUserIds as $uid) {
                        foreach ($interactionsByUser[$uid] ?? [] as $ts) {
                            if ($ts >= $checkStart->toDateTimeString() && $ts <= $checkEnd->toDateTimeString()) {
                                $activeCount++;
                                break; // one interaction is enough to count this user
                            }
                        }
                    }

                    $retention[$w] = round(($activeCount / $cohortSize) * 100, 1);
                }

                $result[] = [
                    'week' => $label,
                    'cohort_size' => $cohortSize,
                    'retention' => $retention,
                ];
            }

            return $result;
        });
    }
}
