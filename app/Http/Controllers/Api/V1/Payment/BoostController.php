<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Enums\AdBoostStatus;
use App\Models\Ad;
use App\Models\AdBoost;
use App\Models\AdInteraction;
use App\Models\BoostPack;
use App\Models\User;
use App\Services\Monetization\BoostService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Owner ad boost endpoints (credit-based, no subscription required).
 */
final readonly class BoostController
{
    public function __construct(private BoostService $boostService) {}

    /**
     * List active boost packs available for purchase.
     * Public endpoint (no auth required to browse packs).
     */
    public function packs(): JsonResponse
    {
        $packs = Cache::remember(
            'boost:packs:active',
            now()->addHour(),
            fn () => BoostPack::query()
                ->active()
                ->get(['id', 'name', 'slug', 'description', 'reach_description', 'duration_days', 'boost_score', 'price_credits', 'is_popular']),
        );

        return response()->json(['data' => $packs]);
    }

    /**
     * Get current boost status + history for an ad (owner only).
     */
    public function status(Request $request, Ad $ad): JsonResponse
    {
        abort_unless($ad->user_id === $request->user()?->id, 403, 'Forbidden');

        $activeBoost = AdBoost::query()
            ->where('ad_id', $ad->id)
            ->active()
            ->with('boostPack:id,name,slug')
            ->first();

        return response()->json([
            'data' => [
                'is_boosted' => $ad->isBoosted(),
                'boost_score' => $ad->boost_score,
                'boost_expires_at' => $ad->boost_expires_at?->toIso8601String(),
                'boosted_at' => $ad->boosted_at?->toIso8601String(),
                'active_boost' => $activeBoost,
            ],
        ]);
    }

    /**
     * Boost an ad by purchasing a boost pack with the owner's credit balance.
     *
     * SEC: Only the ad owner can boost. Credits deducted atomically.
     *      Score and duration come from the pack record (server-side) — no client input.
     */
    public function boost(Request $request, Ad $ad): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($ad->user_id === $user->id, 403, 'Seul le propriétaire peut booster cette annonce.');

        $validated = $request->validate([
            'boost_pack_id' => ['required', 'uuid', 'exists:boost_packs,id'],
        ]);

        /** @var BoostPack $pack */
        $pack = BoostPack::query()->where('id', $validated['boost_pack_id'])->where('is_active', true)->firstOrFail();

        if ($user->point_balance < $pack->price_credits) {
            return response()->json([
                'message' => "Crédits insuffisants. Vous avez {$user->point_balance} crédit(s), ce pack en coûte {$pack->price_credits}.",
                'code' => 'INSUFFICIENT_CREDITS',
            ], 422);
        }

        try {
            $boost = $this->boostService->apply($user, $ad, $pack);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'BOOST_FAILED',
            ], 422);
        }

        return response()->json([
            'message' => "Annonce boostée avec succès pour {$pack->duration_days} jours !",
            'data' => [
                'boost_id' => $boost->id,
                'is_boosted' => true,
                'boost_score' => $boost->boost_score,
                'expires_at' => $boost->expires_at->toIso8601String(),
                'credits_remaining' => $user->point_balance,
            ],
        ]);
    }

    /**
     * Cancel active boost on an ad (owner can cancel early — no refund).
     */
    public function unboost(Request $request, Ad $ad): JsonResponse
    {
        abort_unless($ad->user_id === $request->user()?->id, 403, 'Forbidden');

        AdBoost::query()
            ->where('ad_id', $ad->id)
            ->where('status', AdBoostStatus::Active)
            ->update(['status' => AdBoostStatus::Cancelled]);

        $ad->unboost();

        Cache::forget("boost:status:{$ad->id}");

        return response()->json(['message' => 'Boost annulé. Aucun remboursement de crédits.']);
    }

    /**
     * Boost ROI — compare ad interactions before, during, and after a boost period.
     *
     * Uses the most recent boost for the ad (active or last expired).
     * Compares a "before" window of equal length to the boost duration against
     * the "during" window and, if the boost has expired, a "after" window too.
     *
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/boost/roi",
     *     summary="📈 ROI d'un boost d'annonce",
     *     description="Compare les interactions (vues, contacts, favoris…) avant, pendant et après le boost le plus récent de l'annonce. Retourne des deltas en valeur absolue et en pourcentage.",
     *     tags={"🚀 Boosts"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Stats ROI du boost"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Aucun boost trouvé pour cette annonce")
     * )
     */
    public function boostRoi(Request $request, Ad $ad): JsonResponse
    {
        abort_unless($ad->user_id === $request->user()?->id, 403, 'Forbidden');

        /** @var AdBoost|null $boost */
        $boost = AdBoost::query()
            ->where('ad_id', $ad->id)
            ->whereIn('status', [AdBoostStatus::Active->value, AdBoostStatus::Expired->value])
            ->latest('started_at')
            ->first();

        if (!$boost) {
            return response()->json(['message' => 'Aucun boost trouvé pour cette annonce.'], 404);
        }

        $adIds = [$ad->id];
        $duration = $boost->duration_days;

        $beforeStart = $boost->started_at->copy()->subDays($duration);
        $beforeEnd = $boost->started_at->copy();

        $duringStart = $boost->started_at->copy();
        $duringEnd = $boost->expires_at->copy();

        $before = $this->computeWindowTotals($adIds, $beforeStart, $beforeEnd);
        $during = $this->computeWindowTotals($adIds, $duringStart, $duringEnd);

        $afterData = null;
        if ($boost->expires_at->isPast()) {
            $afterStart = $boost->expires_at->copy();
            $afterEnd = $boost->expires_at->copy()->addDays($duration);
            $afterData = $this->computeWindowTotals($adIds, $afterStart, $afterEnd);
        }

        return response()->json([
            'data' => [
                'boost' => [
                    'id' => $boost->id,
                    'status' => $boost->status,
                    'duration_days' => $duration,
                    'started_at' => $boost->started_at->toIso8601String(),
                    'expires_at' => $boost->expires_at->toIso8601String(),
                ],
                'windows' => [
                    'before' => $before,
                    'during' => $during,
                    'after' => $afterData,
                ],
                'delta' => $this->computeDelta($before, $during),
            ],
        ]);
    }

    /**
     * Compute interaction totals within a specific time window.
     *
     * @param  array<int, string>  $adIds
     * @return array<string, int>
     */
    private function computeWindowTotals(array $adIds, Carbon $from, Carbon $to): array
    {
        $counts = AdInteraction::whereIn('ad_id', $adIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        return [
            'impressions' => (int) ($counts[AdInteraction::TYPE_IMPRESSION] ?? 0),
            'views' => (int) ($counts[AdInteraction::TYPE_VIEW] ?? 0),
            'favorites' => (int) ($counts[AdInteraction::TYPE_FAVORITE] ?? 0),
            'shares' => (int) ($counts[AdInteraction::TYPE_SHARE] ?? 0),
            'contact_clicks' => (int) ($counts[AdInteraction::TYPE_CONTACT_CLICK] ?? 0),
            'phone_clicks' => (int) ($counts[AdInteraction::TYPE_PHONE_CLICK] ?? 0),
            'unlocks' => (int) ($counts[AdInteraction::TYPE_UNLOCK] ?? 0),
        ];
    }

    /**
     * Compute absolute and percentage deltas between two windows.
     *
     * @param  array<string, int>  $before
     * @param  array<string, int>  $during
     * @return array<string, array{absolute: int, percent: float|null}>
     */
    private function computeDelta(array $before, array $during): array
    {
        $delta = [];
        foreach ($during as $key => $duringValue) {
            $beforeValue = $before[$key] ?? 0;
            $absolute = $duringValue - $beforeValue;
            $percent = $beforeValue > 0
                ? round(($absolute / $beforeValue) * 100, 1)
                : null;

            $delta[$key] = ['absolute' => $absolute, 'percent' => $percent];
        }

        return $delta;
    }
}
