<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use App\Models\AdInteraction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Encapsulates all analytics computation logic for ads.
 *
 * Extracted from AdAnalyticsController to keep controllers thin
 * and to allow the computation logic to be reused or tested in isolation.
 */
final class AdAnalyticsService
{
    /**
     * Compute total interaction counts for a set of ad IDs since a given date.
     *
     * @param  array<int, string>  $adIds
     * @return array<string, int|float>
     */
    public function computeTotals(array $adIds, Carbon $since): array
    {
        $counts = AdInteraction::whereIn('ad_id', $adIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        $impressions = (int) ($counts[AdInteraction::TYPE_IMPRESSION] ?? 0);
        $views = (int) ($counts[AdInteraction::TYPE_VIEW] ?? 0);
        $favorites = (int) ($counts[AdInteraction::TYPE_FAVORITE] ?? 0);
        $shares = (int) ($counts[AdInteraction::TYPE_SHARE] ?? 0);
        $contactClicks = (int) ($counts[AdInteraction::TYPE_CONTACT_CLICK] ?? 0);
        $phoneClicks = (int) ($counts[AdInteraction::TYPE_PHONE_CLICK] ?? 0);
        $unlocks = (int) ($counts[AdInteraction::TYPE_UNLOCK] ?? 0);

        $engagementDenominator = max($impressions, 1);
        $conversionDenominator = max($views, 1);

        return [
            'impressions' => $impressions,
            'views' => $views,
            'favorites' => $favorites,
            'shares' => $shares,
            'contact_clicks' => $contactClicks,
            'phone_clicks' => $phoneClicks,
            'unlocks' => $unlocks,
            'conversion_rate' => round(($unlocks / $conversionDenominator) * 100, 2),
            'engagement_rate' => round((($favorites + $shares + $contactClicks) / $engagementDenominator) * 100, 2),
        ];
    }

    /**
     * Compute total interaction counts scoped to a specific owner's ads.
     *
     * @return array<string, int|float>
     */
    public function computeTotalsForOwner(string $userId, Carbon $since): array
    {
        $adIds = $this->ownedAdIdsSubquery($userId)->pluck('id')->toArray();

        return $this->computeTotals($adIds, $since);
    }

    /**
     * Compute daily interaction trends per metric type for a set of ad IDs.
     *
     * @param  array<int, string>  $adIds
     * @return array<string, array<int, array{date: string, count: int}>>
     */
    public function computeTrends(array $adIds, Carbon $since): array
    {
        $rows = AdInteraction::whereIn('ad_id', $adIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('type, DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('type', 'date')
            ->orderBy('date')
            ->get();

        $trends = [];
        foreach ($rows as $row) {
            /** @var string $date */
            $date = $row->getAttribute('date');
            /** @var int $count */
            $count = $row->getAttribute('count');
            $trends[$row->type][] = [
                'date' => $date,
                'count' => (int) $count,
            ];
        }

        return $trends;
    }

    /**
     * Compute daily interaction trends scoped to a specific owner's ads.
     *
     * @return array<string, array<int, array{date: string, count: int}>>
     */
    public function computeTrendsForOwner(string $userId, Carbon $since): array
    {
        $adIds = $this->ownedAdIdsSubquery($userId)->pluck('id')->toArray();

        return $this->computeTrends($adIds, $since);
    }

    /**
     * Compute a per-day breakdown of all metric types for a single ad.
     *
     * @return array<int, array<string, mixed>>
     */
    public function computeDaily(string $adId, Carbon $since): array
    {
        $rows = AdInteraction::where('ad_id', $adId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, type, COUNT(*) as count')
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get();

        /** @var array<string, array<string, mixed>> $byDate */
        $byDate = [];
        foreach ($rows as $row) {
            /** @var string $date */
            $date = $row->getAttribute('date');
            if (!isset($byDate[$date])) {
                $byDate[$date] = [
                    'date' => $date,
                    'impressions' => 0,
                    'views' => 0,
                    'favorites' => 0,
                    'shares' => 0,
                    'contact_clicks' => 0,
                    'phone_clicks' => 0,
                    'unlocks' => 0,
                ];
            }

            $mapping = [
                AdInteraction::TYPE_IMPRESSION => 'impressions',
                AdInteraction::TYPE_VIEW => 'views',
                AdInteraction::TYPE_FAVORITE => 'favorites',
                AdInteraction::TYPE_SHARE => 'shares',
                AdInteraction::TYPE_CONTACT_CLICK => 'contact_clicks',
                AdInteraction::TYPE_PHONE_CLICK => 'phone_clicks',
                AdInteraction::TYPE_UNLOCK => 'unlocks',
            ];

            $key = $mapping[$row->type] ?? null;
            if ($key) {
                $byDate[$date][$key] = (int) $row->getAttribute('count');
            }
        }

        return array_values($byDate);
    }

    /**
     * Compute the top performing ads by view count within a set of ad IDs.
     *
     * @param  array<int, string>  $adIds
     * @return array<int, array<string, mixed>>
     */
    public function computeTopAds(array $adIds, Carbon $since, int $limit = 5): array
    {
        $rows = AdInteraction::whereIn('ad_id', $adIds)
            ->where('created_at', '>=', $since)
            ->whereIn('type', [
                AdInteraction::TYPE_VIEW,
                AdInteraction::TYPE_FAVORITE,
                AdInteraction::TYPE_UNLOCK,
            ])
            ->selectRaw('
                ad_id,
                SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as views,
                SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as favs,
                SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as unlocks
            ', [
                AdInteraction::TYPE_VIEW,
                AdInteraction::TYPE_FAVORITE,
                AdInteraction::TYPE_UNLOCK,
            ])
            ->groupBy('ad_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $viewCounts = $rows->pluck('views', 'ad_id');
        $favCounts = $rows->pluck('favs', 'ad_id');
        $unlockCounts = $rows->pluck('unlocks', 'ad_id');

        $ads = Ad::whereIn('id', $viewCounts->keys())
            ->select(['id', 'title', 'status'])
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($viewCounts as $adId => $views) {
            $ad = $ads[$adId] ?? null;
            if (!$ad) {
                continue;
            }

            $unlocks = (int) ($unlockCounts[$adId] ?? 0);
            $result[] = [
                'ad_id' => $adId,
                'title' => $ad->title,
                'status' => $ad->status,
                'views' => (int) $views,
                'favorites' => (int) ($favCounts[$adId] ?? 0),
                'unlocks' => $unlocks,
                'conversion_rate' => $views > 0 ? round(($unlocks / $views) * 100, 2) : 0,
            ];
        }

        return $result;
    }

    /**
     * Compute the top performing ads scoped to a specific owner.
     *
     * @return array<int, array<string, mixed>>
     */
    public function computeTopAdsForOwner(string $userId, Carbon $since, int $limit = 5): array
    {
        $adIds = $this->ownedAdIdsSubquery($userId)->pluck('id')->toArray();

        return $this->computeTopAds($adIds, $since, $limit);
    }

    /**
     * Compute audience metrics (unique viewers, repeat viewers, favorited-by) for a single ad.
     *
     * @return array{unique_viewers: int, repeat_viewers: int, favorited_by: int}
     */
    public function computeAudience(string $adId, Carbon $since): array
    {
        $viewerCounts = AdInteraction::where('ad_id', $adId)
            ->where('created_at', '>=', $since)
            ->where('type', AdInteraction::TYPE_VIEW)
            ->selectRaw('user_id, COUNT(*) as visit_count')
            ->groupBy('user_id')
            ->get();

        $uniqueViewers = $viewerCounts->count();
        $repeatViewers = $viewerCounts->where('visit_count', '>', 1)->count();

        $favoritedBy = AdInteraction::where('ad_id', $adId)
            ->where('created_at', '>=', $since)
            ->where('type', AdInteraction::TYPE_FAVORITE)
            ->distinct('user_id')
            ->count('user_id');

        return [
            'unique_viewers' => $uniqueViewers,
            'repeat_viewers' => $repeatViewers,
            'favorited_by' => $favoritedBy,
        ];
    }

    /**
     * Return a zeroed-out totals array for when an owner has no ads.
     *
     * @return array<string, int|float>
     */
    public function emptyTotals(): array
    {
        return [
            'impressions' => 0,
            'views' => 0,
            'favorites' => 0,
            'shares' => 0,
            'contact_clicks' => 0,
            'phone_clicks' => 0,
            'unlocks' => 0,
            'conversion_rate' => 0,
            'engagement_rate' => 0,
        ];
    }

    /**
     * Build a subquery that selects only the IDs of ads belonging to an owner.
     *
     * @return Builder<Ad>
     */
    private function ownedAdIdsSubquery(string $userId): Builder
    {
        return Ad::query()
            ->select('id')
            ->where('user_id', $userId);
    }
}
