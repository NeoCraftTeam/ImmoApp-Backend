<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdBoostStatus;
use App\Models\Ad;
use App\Models\AdBoost;
use App\Models\BoostPack;
use App\Models\User;
use App\Services\BoostService;
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
}
