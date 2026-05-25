<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Services\Ad\AdAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
 *
 * All computation is delegated to {@see AdAnalyticsService}.
 */
final readonly class AdAnalyticsController
{
    public function __construct(private AdAnalyticsService $analytics) {}

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
        $period = $request->query('period', '30d');
        $days = $this->parsePeriod($period);

        if (!Ad::query()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'data' => [
                    'period' => $period,
                    'totals' => $this->analytics->emptyTotals(),
                    'trends' => [],
                    'top_ads' => [],
                ],
            ]);
        }

        $cacheKey = "analytics:overview:{$user->id}:{$period}";
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user, $days, $period) {
            $since = now()->subDays($days);

            return [
                'period' => $period,
                'totals' => $this->analytics->computeTotalsForOwner($user->id, $since),
                'trends' => $this->analytics->computeTrendsForOwner($user->id, $since),
                'top_ads' => $this->analytics->computeTopAdsForOwner($user->id, $since, 5),
            ];
        });

        return response()->json(['data' => $data]);
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

        if ($ad->user_id !== $user->id) {
            return response()->json(['message' => 'Cette annonce ne vous appartient pas.'], 403);
        }

        $period = $request->query('period', '30d');
        $days = $this->parsePeriod($period);

        $cacheKey = "analytics:ad:{$ad->id}:{$period}";
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($ad, $days, $period) {
            $since = now()->subDays($days);

            $totals = $this->analytics->computeTotals([$ad->id], $since);
            $daily = $this->analytics->computeDaily($ad->id, $since);

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

            $audience = $this->analytics->computeAudience($ad->id, $since);

            return [
                'period' => $period,
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
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Parse a period string into the equivalent number of days.
     */
    private function parsePeriod(string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
    }
}
