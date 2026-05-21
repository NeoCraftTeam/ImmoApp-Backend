<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReservationStatus;
use App\Models\Ad;
use App\Models\AdBoost;
use App\Models\Conversation;
use App\Models\LeaseContract;
use App\Models\TentativeReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Owner dashboard KPIs — aggregate stats for the authenticated landlord.
 *
 * Audit Item 9: occupancy rate, active leases, pending viewings, boosts, unread messages.
 */
final class OwnerDashboardController
{
    /**
     * @OA\Get(
     *     path="/api/v1/my/stats",
     *     summary="📊 Dashboard KPIs bailleur",
     *     description="Retourne les indicateurs clés du tableau de bord du bailleur authentifié :
     *
     *     - **active_ads_count** : annonces publiées (AVAILABLE + RESERVED)
     *     - **active_leases_count** : baux actifs (lease_end >= aujourd'hui)
     *     - **occupancy_rate** : taux d'occupation (leases actifs / annonces publiées, %)
     *     - **monthly_rent_total_xaf** : somme des loyers mensuels des baux actifs (XAF)
     *     - **pending_viewings_count** : visites en attente de confirmation
     *     - **confirmed_viewings_count** : visites confirmées à venir
     *     - **active_boosts_count** : boosts en cours
     *     - **unread_conversations_count** : conversations avec des messages non lus",
     *     tags={"📊 Statistics"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="KPIs du dashboard",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="active_ads_count", type="integer", example=5),
     *                 @OA\Property(property="active_leases_count", type="integer", example=3),
     *                 @OA\Property(property="occupancy_rate", type="number", example=60.0),
     *                 @OA\Property(property="monthly_rent_total_xaf", type="number", example=750000),
     *                 @OA\Property(property="pending_viewings_count", type="integer", example=2),
     *                 @OA\Property(property="confirmed_viewings_count", type="integer", example=1),
     *                 @OA\Property(property="active_boosts_count", type="integer", example=1),
     *                 @OA\Property(property="unread_conversations_count", type="integer", example=4)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $data = Cache::remember("owner:stats:{$userId}", now()->addMinutes(5), function () use ($userId): array {
            $activeAdsCount = Ad::query()
                ->where('user_id', $userId)
                ->publiclyListed()
                ->count();

            $activeLeasesCount = LeaseContract::query()
                ->where('user_id', $userId)
                ->whereDate('lease_end', '>=', now()->toDateString())
                ->count();

            $occupancyRate = $activeAdsCount > 0
                ? min(100.0, round(($activeLeasesCount / $activeAdsCount) * 100, 1))
                : 0.0;

            $monthlyRentTotal = (float) LeaseContract::query()
                ->where('user_id', $userId)
                ->whereDate('lease_end', '>=', now()->toDateString())
                ->sum('monthly_rent');

            $adIds = Ad::query()->where('user_id', $userId)->pluck('id');

            $pendingViewingsCount = TentativeReservation::query()
                ->whereIn('ad_id', $adIds)
                ->where('status', ReservationStatus::Pending)
                ->count();

            $confirmedViewingsCount = TentativeReservation::query()
                ->whereIn('ad_id', $adIds)
                ->where('status', ReservationStatus::Confirmed)
                ->count();

            $activeBoostsCount = AdBoost::query()
                ->where('user_id', $userId)
                ->active()
                ->count();

            $unreadConversationsCount = Conversation::query()
                ->where('landlord_id', $userId)
                ->whereNotNull('last_message_at')
                ->where(function ($q): void {
                    $q->whereNull('landlord_last_read_at')
                        ->orWhereColumn('landlord_last_read_at', '<', 'last_message_at');
                })
                ->count();

            return [
                'active_ads_count' => $activeAdsCount,
                'active_leases_count' => $activeLeasesCount,
                'occupancy_rate' => $occupancyRate,
                'monthly_rent_total_xaf' => $monthlyRentTotal,
                'pending_viewings_count' => $pendingViewingsCount,
                'confirmed_viewings_count' => $confirmedViewingsCount,
                'active_boosts_count' => $activeBoostsCount,
                'unread_conversations_count' => $unreadConversationsCount,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
