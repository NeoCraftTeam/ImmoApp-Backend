<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * RecommendationEngine – Weighted Scoring with Diversity Injection
 *
 * Scoring Formula:
 *   score = (type_match × 40) + (city_match × 25) + (budget_fit × 20)
 *         + (freshness × 10) + (popularity × 5) + boost_bonus
 *
 * Diversity: 20% of results are "exploration" ads outside the user's profile.
 *
 * Cold Start: When user has no interactions, return a mix of:
 *   - Trending (most viewed in last 7 days)
 *   - Boosted ads
 *   - Latest ads
 *
 * @see \App\Http\Controllers\Api\V1\RecommendationController
 */
final class RecommendationEngine
{
    /** Maximum number of results to return */
    private const int RESULT_LIMIT = 15;

    /** Number of interactions to analyze */
    private const int PROFILE_DEPTH = 30;

    /** Days after which an interaction loses half its weight */
    private const int DECAY_HALF_LIFE_DAYS = 14;

    /** Fraction of results reserved for diversity */
    private const float DIVERSITY_RATIO = 0.2;

    /** Cache TTL in minutes */
    private const int CACHE_TTL_MINUTES = 10;

    /** Standard eager-load relations for ads */
    private const array AD_EAGER_LOADS = ['quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'agency'];

    // ── Weights ───────────────────────────────────────────────────────
    private const int W_TYPE = 40;

    private const int W_CITY = 25;

    private const int W_BUDGET = 20;

    private const int W_FRESHNESS = 10;

    private const int W_POPULARITY = 5;

    private const int BOOST_BONUS = 15;

    // ══════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ══════════════════════════════════════════════════════════════════

    /**
     * Get recommendations for a user.
     *
     * @return array{ads: Collection<int, Ad>, meta: array<string, mixed>}
     */
    public function recommend(User $user): array
    {
        $cacheKey = "reco_v2_user_{$user->id}";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($user): array {
            $profile = $this->buildUserProfile($user);

            if ($profile === null) {
                return $this->coldStart($user);
            }

            return $this->personalizedRecommendations($user, $profile);
        });
    }

    // ══════════════════════════════════════════════════════════════════
    // USER PROFILE
    // ══════════════════════════════════════════════════════════════════

    /**
     * Build a user preference profile from their last N interactions.
     *
     * Applies temporal decay: recent interactions count more.
     *
     * @return array{
     *   type_weights: array<int|string, float>,
     *   city_weights: array<int|string, float>,
     *   avg_price: float,
     *   min_price: float,
     *   max_price: float,
     *   seen_ad_ids: array<int, int|string>
     * }|null
     */
    private function buildUserProfile(User $user): ?array
    {
        $interactions = AdInteraction::where('user_id', $user->id)
            ->whereIn('type', [
                AdInteraction::TYPE_VIEW,
                AdInteraction::TYPE_FAVORITE,
                AdInteraction::TYPE_UNLOCK,
            ])
            ->whereNotNull('ad_id')
            ->with('ad.quarter')
            ->latest('created_at')
            ->take(self::PROFILE_DEPTH)
            ->get();

        if ($interactions->isEmpty()) {
            return null;
        }

        $now = Carbon::now();
        $typeWeights = [];
        $cityWeights = [];
        $prices = [];
        $seenAdIds = [];

        // Signal strength: unlock > favorite > view
        $signalMultiplier = [
            AdInteraction::TYPE_UNLOCK => 3.0,
            AdInteraction::TYPE_FAVORITE => 2.0,
            AdInteraction::TYPE_VIEW => 1.0,
        ];

        foreach ($interactions as $interaction) {
            $ad = $interaction->ad;
            if (!$ad) {
                continue;
            }

            $seenAdIds[] = $ad->id;

            // Temporal decay: e^(-λt), half-life = DECAY_HALF_LIFE_DAYS
            $daysAgo = $now->diffInDays($interaction->created_at);
            $decay = exp(-0.693 * $daysAgo / self::DECAY_HALF_LIFE_DAYS);

            $signal = $signalMultiplier[$interaction->type] ?? 1.0;
            $weight = $decay * $signal;

            // Accumulate type preference
            if ($ad->type_id) {
                $typeWeights[$ad->type_id] = ($typeWeights[$ad->type_id] ?? 0) + $weight;
            }

            // Accumulate city preference
            $cityId = $ad->quarter?->city_id;
            if ($cityId) {
                $cityWeights[$cityId] = ($cityWeights[$cityId] ?? 0) + $weight;
            }

            // Collect prices for budget range
            if ($ad->price > 0) {
                $prices[] = (float) $ad->price;
            }
        }

        if (empty($prices)) {
            return null;
        }

        $avgPrice = array_sum($prices) / count($prices);

        return [
            'type_weights' => $typeWeights,
            'city_weights' => $cityWeights,
            'avg_price' => $avgPrice,
            'min_price' => $avgPrice * 0.5,
            'max_price' => $avgPrice * 1.8,
            'seen_ad_ids' => array_unique($seenAdIds),
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    // PERSONALIZED RECOMMENDATIONS
    // ══════════════════════════════════════════════════════════════════

    /**
     * Score and rank candidate ads based on the user's profile.
     *
     * @param  array<string, mixed>  $profile
     * @return array{ads: Collection<int, Ad>, meta: array<string, mixed>}
     */
    private function personalizedRecommendations(User $user, array $profile): array
    {
        $mainLimit = (int) ceil(self::RESULT_LIMIT * (1 - self::DIVERSITY_RATIO));
        $diversityLimit = self::RESULT_LIMIT - $mainLimit;

        // ── Popularity data (view counts last 30 days) ────────────────
        $popularityMap = $this->getPopularityMap();

        // ── Max values for normalization ──────────────────────────────
        $maxTypeWeight = max($profile['type_weights'] ?: [1]);
        $maxCityWeight = max($profile['city_weights'] ?: [1]);
        $maxPopularity = max($popularityMap ?: [1]);

        // ── Candidate ads ────────────────────────────────────────────
        $candidates = Ad::with(self::AD_EAGER_LOADS)
            ->where('status', AdStatus::AVAILABLE)
            ->whereNotIn('id', $profile['seen_ad_ids'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->get();

        // ── Score each candidate ─────────────────────────────────────
        $scored = [];
        $freshestDate = $candidates->max('created_at') ?? now();
        $oldestDate = $candidates->min('created_at') ?? now()->subMonth();
        $dateRangeSeconds = max(1, $freshestDate->diffInSeconds($oldestDate));

        foreach ($candidates as $ad) {
            $score = 0.0;

            // 1. Type match (0–40)
            $typeWeight = $profile['type_weights'][$ad->type_id] ?? 0;
            $score += self::W_TYPE * ($typeWeight / $maxTypeWeight);

            // 2. City match (0–25)
            $cityId = $ad->quarter?->city_id;
            $cityWeight = $cityId ? ($profile['city_weights'][$cityId] ?? 0) : 0;
            $score += self::W_CITY * ($cityWeight / $maxCityWeight);

            // 3. Budget fit (0–20): gaussian curve centered on avg_price
            $price = (float) $ad->price;
            if ($price > 0 && $profile['avg_price'] > 0) {
                $sigma = ($profile['max_price'] - $profile['min_price']) / 4;
                $diff = abs($price - $profile['avg_price']);
                $budgetFit = exp(-0.5 * ($diff / max($sigma, 1)) ** 2);
                $score += self::W_BUDGET * $budgetFit;
            }

            // 4. Freshness (0–10): newer = higher
            $ageSeconds = max(0, $freshestDate->diffInSeconds($ad->created_at));
            $freshnessScore = 1 - ($ageSeconds / max($dateRangeSeconds, 1));
            $score += self::W_FRESHNESS * $freshnessScore;

            // 5. Popularity (0–5)
            $viewCount = $popularityMap[$ad->id] ?? 0;
            $score += self::W_POPULARITY * ($viewCount / max($maxPopularity, 1));

            // Boost bonus
            if ($ad->isBoosted()) {
                $score += self::BOOST_BONUS;
            }

            $scored[] = ['ad' => $ad, 'score' => round($score, 2)];
        }

        // Sort by score DESC
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Take top N for main results
        $mainResults = array_slice($scored, 0, $mainLimit);

        // ── Diversity injection ───────────────────────────────────────
        $diversityAds = $this->getDiversityAds(
            $profile,
            array_column($mainResults, 'ad'),
            $diversityLimit
        );

        // Merge and collect
        $allAds = collect(array_column($mainResults, 'ad'))
            ->merge($diversityAds);

        return [
            'ads' => $allAds,
            'meta' => [
                'source' => 'personalized',
                'algorithm' => 'weighted_scoring_v2',
                'profile_signals' => count($profile['type_weights']),
                'candidates_scored' => count($scored),
                'diversity_injected' => $diversityAds->count(),
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    // COLD START
    // ══════════════════════════════════════════════════════════════════

    /**
     * Cold start strategy for users with no interaction history.
     *
     * Returns a mix of: trending + boosted + latest.
     *
     * @return array{ads: Collection<int, Ad>, meta: array<string, mixed>}
     */
    private function coldStart(User $user): array
    {
        $limit = self::RESULT_LIMIT;
        $trendingLimit = (int) ceil($limit * 0.4);
        $boostedLimit = (int) ceil($limit * 0.3);
        $latestLimit = $limit - $trendingLimit - $boostedLimit;

        $collected = collect();
        $excludeIds = [];

        // 1. Trending: most viewed in last 7 days
        $trendingIds = AdInteraction::where('type', AdInteraction::TYPE_VIEW)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('ad_id')
            ->select('ad_id')
            ->groupBy('ad_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($trendingLimit)
            ->pluck('ad_id');

        if ($trendingIds->isNotEmpty()) {
            $trending = Ad::with(self::AD_EAGER_LOADS)
                ->whereIn('id', $trendingIds)
                ->where('status', AdStatus::AVAILABLE)
                ->get();
            $collected = $collected->merge($trending);
            $excludeIds = $collected->pluck('id')->toArray();
        }

        // 2. Boosted
        $boosted = Ad::with(self::AD_EAGER_LOADS)
            ->where('status', AdStatus::AVAILABLE)
            ->whereNotIn('id', $excludeIds)
            ->boosted()
            ->orderByBoost()
            ->take($boostedLimit)
            ->get();
        $collected = $collected->merge($boosted);
        $excludeIds = $collected->pluck('id')->toArray();

        // 3. Latest (fill remaining)
        $remaining = $limit - $collected->count();
        if ($remaining > 0) {
            $latest = Ad::with(self::AD_EAGER_LOADS)
                ->where('status', AdStatus::AVAILABLE)
                ->whereNotIn('id', $excludeIds)
                ->latest()
                ->take($remaining)
                ->get();
            $collected = $collected->merge($latest);
        }

        return [
            'ads' => $collected->take($limit),
            'meta' => [
                'source' => 'cold_start',
                'algorithm' => 'trending_boosted_latest',
                'trending_count' => $trendingIds->count(),
                'boosted_count' => $boosted->count(),
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════

    /**
     * Get ad view counts for the last 30 days (popularity signal).
     *
     * @return array<int|string, int> ad_id => view_count
     */
    private function getPopularityMap(): array
    {
        return AdInteraction::where('type', AdInteraction::TYPE_VIEW)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('ad_id')
            ->selectRaw('ad_id, COUNT(*) as view_count')
            ->groupBy('ad_id')
            ->pluck('view_count', 'ad_id')
            ->toArray();
    }

    /**
     * Get diversity/exploration ads that don't match the user's profile.
     *
     * These are intentionally outside the user's comfort zone to prevent filter bubbles.
     *
     * @param  array<string, mixed>  $profile
     * @param  array<int, Ad>  $excludeAds
     * @return Collection<int, Ad>
     */
    private function getDiversityAds(array $profile, array $excludeAds, int $limit): Collection
    {
        $excludeIds = array_merge(
            $profile['seen_ad_ids'],
            array_map(fn ($ad) => $ad->id, $excludeAds)
        );

        $preferredTypeIds = array_keys($profile['type_weights']);

        return Ad::with(self::AD_EAGER_LOADS)
            ->where('status', AdStatus::AVAILABLE)
            ->whereNotIn('id', $excludeIds)
            ->where(function (Builder $query) use ($preferredTypeIds, $profile): void {
                // Outside preferred types OR outside budget range
                if (!empty($preferredTypeIds)) {
                    $query->whereNotIn('type_id', $preferredTypeIds);
                }
                $query->orWhere('price', '<', $profile['min_price'])
                    ->orWhere('price', '>', $profile['max_price']);
            })
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }
}
