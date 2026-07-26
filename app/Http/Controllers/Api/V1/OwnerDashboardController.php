<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReservationStatus;
use App\Models\Ad;
use App\Models\AdBoost;
use App\Models\Conversation;
use App\Models\Expense;
use App\Models\LeaseContract;
use App\Models\RentPayment;
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
     *                 @OA\Property(property="unread_conversations_count", type="integer", example=4),
     *                 @OA\Property(property="expenses_total_xaf_30d", type="number", example=125000),
     *                 @OA\Property(property="rent_collected_xaf_30d", type="integer", example=450000)
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

            // Use the lifecycle status flag — kept in sync by the
            // `leases:expire-overdue` scheduled command — instead of
            // re-deriving from `lease_end`. This way terminated /
            // archived leases drop out of the KPI immediately.
            $activeLeasesCount = LeaseContract::query()
                ->where('user_id', $userId)
                ->active()
                ->count();

            $occupancyRate = $activeAdsCount > 0
                ? min(100.0, round(($activeLeasesCount / $activeAdsCount) * 100, 1))
                : 0.0;

            $monthlyRentTotal = (float) LeaseContract::query()
                ->where('user_id', $userId)
                ->active()
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

            $expensesTotal30d = (float) Expense::query()
                ->where('user_id', $userId)
                ->whereDate('expense_date', '>=', now()->subDays(30)->toDateString())
                ->sum('amount');

            // Actual rent collected (out-of-band ledger) — distinct from
            // `monthly_rent_total_xaf` which is the accrual figure derived
            // from active lease contracts.
            $rentCollected30d = (int) RentPayment::query()
                ->whereIn('lease_contract_id', LeaseContract::query()->where('user_id', $userId)->select('id'))
                ->whereDate('received_at', '>=', now()->subDays(30)->toDateString())
                ->sum('amount');

            return [
                'active_ads_count' => $activeAdsCount,
                'active_leases_count' => $activeLeasesCount,
                'occupancy_rate' => $occupancyRate,
                'monthly_rent_total_xaf' => $monthlyRentTotal,
                'pending_viewings_count' => $pendingViewingsCount,
                'confirmed_viewings_count' => $confirmedViewingsCount,
                'active_boosts_count' => $activeBoostsCount,
                'unread_conversations_count' => $unreadConversationsCount,
                'expenses_total_xaf_30d' => $expensesTotal30d,
                'rent_collected_xaf_30d' => $rentCollected30d,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
