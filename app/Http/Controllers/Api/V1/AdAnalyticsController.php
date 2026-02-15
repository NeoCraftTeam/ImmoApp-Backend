<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Models\AdInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * Ad Analytics Dashboard for landlords and agencies.
 *
 * Provides Facebook Insights / TikTok Analytics–style metrics:
 * - Impressions, views, favorites, shares, contact clicks, phone clicks, unlocks
 * - Conversion funnel (impressions → views → contacts → unlocks)
 * - Engagement rate, conversion rate
 * - Daily time-series data
 * - Top performing ads
 * - Audience analysis (unique vs repeat viewers)
 */
final class AdAnalyticsController
{
    /**
     * Publisher Overview — aggregate analytics for all the user's ads.
     *
     * Returns totals, daily trends, and top-performing ads for a given period.
     *
     * @OA\Get(
     *     path="/api/v1/my/ads/analytics",
     *     summary="📊 Dashboard analytics — Vue d'ensemble (toutes mes annonces)",
     *     description="Retourne les métriques agrégées de toutes les annonces du bailleur/agence :
     *
     *     - **Totaux** : impressions, vues, favoris, partages, contacts, appels, déblocages
     *     - **Taux** : conversion (unlocks/views), engagement ((fav+shares+contacts)/impressions)
     *     - **Tendances** : données quotidiennes par type de métrique
     *     - **Top Ads** : les 5 annonces les plus performantes
     *
     *     Paramètre `period` : `7d`, `30d`, `90d` (défaut : `30d`).",
     *     tags={"📊 Analytics"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="period", in="query", required=false,
     *         description="Période d'analyse : 7d, 30d, 90d",
     *
     *         @OA\Schema(type="string", enum={"7d","30d","90d"}, default="30d")
     *     ),
     *
     *     @OA\Response(response=200, description="Analytics overview",
     *
     *         @OA\JsonContent(type="object",
     *
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="period", type="string", example="30d"),
     *                 @OA\Property(property="totals", type="object",
     *                     @OA\Property(property="impressions", type="integer", example=12450),
     *                     @OA\Property(property="views", type="integer", example=3200),
     *                     @OA\Property(property="favorites", type="integer", example=180),
     *                     @OA\Property(property="shares", type="integer", example=45),
     *                     @OA\Property(property="contact_clicks", type="integer", example=120),
     *                     @OA\Property(property="phone_clicks", type="integer", example=85),
     *                     @OA\Property(property="unlocks", type="integer", example=62),
     *                     @OA\Property(property="conversion_rate", type="number", example=1.94),
     *                     @OA\Property(property="engagement_rate", type="number", example=2.77)
     *                 ),
     *                 @OA\Property(property="trends", type="object"),
     *                 @OA\Property(property="top_ads", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $this->parsePeriod($request->query('period', '30d'));
        $since = now()->subDays($days);

        // Get all ad IDs owned by the user
        $adIds = Ad::where('user_id', $user->id)->pluck('id');

        if ($adIds->isEmpty()) {
            return response()->json([
                'data' => [
                    'period' => $request->query('period', '30d'),
                    'totals' => $this->emptyTotals(),
                    'trends' => [],
                    'top_ads' => [],
                ],
            ]);
        }

        // ── Totals ────────────────────────────────────────────────────
        $totals = $this->computeTotals($adIds, $since);

        // ── Daily trends ──────────────────────────────────────────────
        $trends = $this->computeTrends($adIds, $since);

        // ── Top 5 ads by views ────────────────────────────────────────
        $topAds = $this->computeTopAds($adIds, $since, 5);

        return response()->json([
            'data' => [
                'period' => $request->query('period', '30d'),
                'totals' => $totals,
                'trends' => $trends,
                'top_ads' => $topAds,
            ],
        ]);
    }

    /**
     * Single Ad Analytics — detailed metrics for one ad.
     *
     * Returns totals, daily breakdown, conversion funnel, and audience analysis.
     *
     * @OA\Get(
     *     path="/api/v1/my/ads/{ad}/analytics",
     *     summary="📊 Analytics détaillées d'une annonce",
     *     description="Retourne les métriques détaillées d'une annonce spécifique :
     *
     *     - **Totaux** : toutes les métriques d'interaction
     *     - **Quotidien** : breakdown jour par jour
     *     - **Entonnoir** : impressions → views → contacts → unlocks
     *     - **Audience** : viewers uniques, récurrents, favorited_by
     *
     *     L'annonce doit appartenir à l'utilisateur authentifié.",
     *     tags={"📊 Analytics"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="period", in="query", required=false,
     *
     *         @OA\Schema(type="string", enum={"7d","30d","90d"}, default="30d")
     *     ),
     *
     *     @OA\Response(response=200, description="Ad analytics detail"),
     *     @OA\Response(response=403, description="L'annonce ne vous appartient pas"),
     *     @OA\Response(response=404, description="Annonce introuvable")
     * )
     */
    public function show(Request $request, Ad $ad): JsonResponse
    {
        $user = $request->user();

        // Authorization: only the ad owner can view analytics
        if ($ad->user_id !== $user->id) {
            return response()->json(['message' => 'Cette annonce ne vous appartient pas.'], 403);
        }

        $days = $this->parsePeriod($request->query('period', '30d'));
        $since = now()->subDays($days);
        $adIds = collect([$ad->id]);

        // ── Totals ────────────────────────────────────────────────────
        $totals = $this->computeTotals($adIds, $since);

        // ── Daily breakdown ───────────────────────────────────────────
        $daily = $this->computeDaily($ad->id, $since);

        // ── Conversion funnel ─────────────────────────────────────────
        $funnel = [
            'impressions' => $totals['impressions'],
            'views' => $totals['views'],
            'contacts' => $totals['contact_clicks'] + $totals['phone_clicks'],
            'unlocks' => $totals['unlocks'],
            'impression_to_view_rate' => $totals['impressions'] > 0
                ? round(($totals['views'] / $totals['impressions']) * 100, 2)
                : 0,
            'view_to_contact_rate' => $totals['views'] > 0
                ? round((($totals['contact_clicks'] + $totals['phone_clicks']) / $totals['views']) * 100, 2)
                : 0,
            'view_to_unlock_rate' => $totals['views'] > 0
                ? round(($totals['unlocks'] / $totals['views']) * 100, 2)
                : 0,
        ];

        // ── Audience analysis ─────────────────────────────────────────
        $audience = $this->computeAudience($ad->id, $since);

        return response()->json([
            'data' => [
                'period' => $request->query('period', '30d'),
                'ad' => [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'status' => $ad->status,
                    'created_at' => $ad->created_at,
                ],
                'totals' => $totals,
                'daily' => $daily,
                'funnel' => $funnel,
                'audience' => $audience,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════

    /**
     * Parse period string to number of days.
     */
    private function parsePeriod(string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
    }

    /**
     * Compute total counts per interaction type for a set of ad IDs.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $adIds
     * @return array<string, int|float>
     */
    private function computeTotals($adIds, $since): array
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
     * @return array<string, int|float>
     */
    private function emptyTotals(): array
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
     * Compute daily trends per metric type for overview.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $adIds
     * @return array<string, array<int, array{date: string, count: int}>>
     */
    private function computeTrends($adIds, $since): array
    {
        $rows = AdInteraction::whereIn('ad_id', $adIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('type, DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('type', 'date')
            ->orderBy('date')
            ->get();

        $trends = [];
        foreach ($rows as $row) {
            $trends[$row->type][] = [
                'date' => $row->date,
                'count' => (int) $row->count,
            ];
        }

        return $trends;
    }

    /**
     * Compute daily breakdown for a single ad (all metrics per day).
     *
     * @return array<int, array<string, mixed>>
     */
    private function computeDaily(mixed $adId, $since): array
    {
        $rows = AdInteraction::where('ad_id', $adId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, type, COUNT(*) as count')
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get();

        // Pivot: group by date, spread types as columns
        $byDate = [];
        foreach ($rows as $row) {
            $date = $row->date;
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
                $byDate[$date][$key] = (int) $row->count;
            }
        }

        return array_values($byDate);
    }

    /**
     * Compute top performing ads by total views.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $adIds
     * @return array<int, array<string, mixed>>
     */
    private function computeTopAds($adIds, $since, int $limit): array
    {
        // Get view counts per ad
        $viewCounts = AdInteraction::whereIn('ad_id', $adIds)
            ->where('created_at', '>=', $since)
            ->where('type', AdInteraction::TYPE_VIEW)
            ->selectRaw('ad_id, COUNT(*) as views')
            ->groupBy('ad_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->pluck('views', 'ad_id');

        if ($viewCounts->isEmpty()) {
            return [];
        }

        // Get favorite counts
        $favCounts = AdInteraction::whereIn('ad_id', $viewCounts->keys())
            ->where('created_at', '>=', $since)
            ->where('type', AdInteraction::TYPE_FAVORITE)
            ->selectRaw('ad_id, COUNT(*) as favs')
            ->groupBy('ad_id')
            ->pluck('favs', 'ad_id');

        // Get unlock counts
        $unlockCounts = AdInteraction::whereIn('ad_id', $viewCounts->keys())
            ->where('created_at', '>=', $since)
            ->where('type', AdInteraction::TYPE_UNLOCK)
            ->selectRaw('ad_id, COUNT(*) as unlocks')
            ->groupBy('ad_id')
            ->pluck('unlocks', 'ad_id');

        // Load ads
        $ads = Ad::whereIn('id', $viewCounts->keys())->get()->keyBy('id');

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
     * Compute audience metrics for a single ad.
     *
     * @return array{unique_viewers: int, repeat_viewers: int, favorited_by: int}
     */
    private function computeAudience(mixed $adId, $since): array
    {
        // Unique viewers
        $viewerCounts = AdInteraction::where('ad_id', $adId)
            ->where('created_at', '>=', $since)
            ->where('type', AdInteraction::TYPE_VIEW)
            ->selectRaw('user_id, COUNT(*) as visit_count')
            ->groupBy('user_id')
            ->get();

        $uniqueViewers = $viewerCounts->count();
        $repeatViewers = $viewerCounts->where('visit_count', '>', 1)->count();

        // Users who favorited
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
}
