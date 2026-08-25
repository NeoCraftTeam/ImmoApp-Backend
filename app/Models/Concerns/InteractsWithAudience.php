<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\AdInteraction;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Audience-facing reads for an ad: favorites, unlock/access gating, and
 * recent view counts. Extracted from the Ad model to keep the model
 * focused on persistence, relations and scopes.
 */
trait InteractsWithAudience
{
    /** Get the number of views in the last 30 days. */
    public function recentViewCount(): int
    {
        return $this->interactions()
            ->where('type', AdInteraction::TYPE_VIEW)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
    }

    /**
     * Check if a user has favorited this ad.
     *
     * For single-ad lookups (detail page) pass no second argument — two queries
     * will be fired. For list contexts, call `loadFavoritedIds()` once for the
     * entire collection and pass the result here to avoid N+1.
     *
     * @param  array<string>|null  $preloadedFavoritedIds  Output of `Ad::loadFavoritedIds()`
     */
    public function isFavoritedBy(?User $user, ?array $preloadedFavoritedIds = null): bool
    {
        if (!$user) {
            return false;
        }

        if ($preloadedFavoritedIds !== null) {
            return in_array($this->id, $preloadedFavoritedIds, true);
        }

        // Per-request static batch loader: first call for a given user fires ONE
        // GROUP BY query loading all their favorites, then every subsequent ad in
        // the same request hits the in-memory cache. Eliminates the 2N query N+1.
        /** @var array<string, array<string, array<string, int>>> $requestCache */
        static $requestCache = [];

        if (!array_key_exists($user->id, $requestCache)) {
            $rows = AdInteraction::where('user_id', $user->id)
                ->whereIn('type', [AdInteraction::TYPE_FAVORITE, AdInteraction::TYPE_UNFAVORITE])
                ->selectRaw('ad_id, type, COUNT(*) as cnt')
                ->groupBy('ad_id', 'type')
                ->get();

            $byAd = [];
            foreach ($rows as $row) {
                /** @var object{ad_id: string, type: string, cnt: int|string} $row */
                $byAd[$row->ad_id][$row->type] = (int) $row->cnt;
            }

            $requestCache[$user->id] = $byAd;
        }

        $byAd = $requestCache[$user->id];

        return ($byAd[$this->id][AdInteraction::TYPE_FAVORITE] ?? 0)
            > ($byAd[$this->id][AdInteraction::TYPE_UNFAVORITE] ?? 0);
    }

    /**
     * Bulk-load the ad IDs that a user has favorited — one query for the
     * whole set, suitable for eliminating N+1 in paginated listing responses.
     *
     * Usage:
     *   $favorited = Ad::loadFavoritedIds($user->id, $ads->pluck('id')->all());
     *   // then in each resource: $ad->isFavoritedBy($user, $favorited)
     *
     * @param  array<string>  $adIds
     * @return array<string>
     */
    public static function loadFavoritedIds(string $userId, array $adIds): array
    {
        if ($adIds === []) {
            return [];
        }

        $rows = AdInteraction::whereIn('ad_id', $adIds)
            ->where('user_id', $userId)
            ->whereIn('type', [AdInteraction::TYPE_FAVORITE, AdInteraction::TYPE_UNFAVORITE])
            ->selectRaw('ad_id, type, COUNT(*) as cnt')
            ->groupBy('ad_id', 'type')
            ->get();

        /** @var array<string, array<string, int>> $byAd */
        $byAd = [];
        foreach ($rows as $row) {
            /** @var object{ad_id: string, type: string, cnt: int|string} $row */
            $byAd[$row->ad_id][$row->type] = (int) $row->cnt;
        }

        return array_values(array_filter(
            array_keys($byAd),
            static fn (string $adId): bool => ($byAd[$adId][AdInteraction::TYPE_FAVORITE] ?? 0)
                > ($byAd[$adId][AdInteraction::TYPE_UNFAVORITE] ?? 0),
        ));
    }

    /**
     * Check if the ad is unlocked for a specific user.
     *
     * Uses a per-request static batch loader keyed by user: the first call for a
     * given user fires ONE query loading every ad they've unlocked, then every
     * subsequent ad in the same request (e.g. a full AdResource page) resolves
     * from the in-memory set. Mirrors {@see isFavoritedBy()} and eliminates the
     * per-ad `exists()` N+1 AdResource previously triggered on list endpoints.
     */
    public function isUnlockedFor(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Owner always has access
        if ($this->user_id === $user->id) {
            return true;
        }

        /** @var array<string, array<string, true>> $requestCache */
        static $requestCache = [];

        if (!array_key_exists($user->id, $requestCache)) {
            $requestCache[$user->id] = array_fill_keys(
                UnlockedAd::where('user_id', $user->id)->pluck('ad_id')->all(),
                true,
            );
        }

        return isset($requestCache[$user->id][$this->id]);
    }

    /**
     * Get all images for the ad (images are always visible).
     */
    public function getAccessibleImages(?User $user): Collection
    {
        $media = $this->getMedia('images');

        if ($this->isUnlockedFor($user)) {
            return $media;
        }

        return $media->take(1);
    }
}
