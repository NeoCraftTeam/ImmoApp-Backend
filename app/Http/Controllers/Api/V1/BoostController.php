<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Models\Agency;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Owner ad boost endpoints.
 */
final class BoostController
{
    /**
     * Get available boost plans.
     */
    public function plans(): JsonResponse
    {
        // Boost plans rarely change; cache the slimmed projection for 1 hour.
        $plans = Cache::remember(
            'boost:plans:active',
            now()->addHour(),
            fn () => SubscriptionPlan::query()
                ->where('is_active', true)
                ->whereNotNull('boost_score')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'price', 'boost_score', 'boost_duration_days', 'description']),
        );

        return response()->json(['data' => $plans]);
    }

    /**
     * Get current boost status for an ad.
     */
    public function status(Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => [
                'is_boosted' => $ad->isBoosted(),
                'boost_score' => $ad->boost_score,
                'boost_expires_at' => $ad->boost_expires_at,
                'boosted_at' => $ad->boosted_at,
            ],
        ]);
    }

    /**
     * Boost an ad manually (owner self-service).
     *
     * SEC: Requires an active subscription with a boost entitlement.
     * Score and duration are derived from the subscription plan to prevent abuse.
     */
    public function boost(Request $request, Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        /** @var User $user */
        $user = $request->user();
        /** @var Agency|null $agency */
        $agency = $user->agency;

        if (!$agency || !$agency->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Un abonnement actif est requis pour booster une annonce.',
            ], 403);
        }

        $subscription = $agency->getCurrentSubscription();
        $plan = $subscription?->plan;

        if (!$plan || !$plan->boost_score) {
            return response()->json([
                'message' => 'Votre plan ne permet pas de booster les annonces.',
            ], 403);
        }

        $ad->boost($plan->boost_score, $plan->boost_duration_days ?? 7);

        return response()->json([
            'message' => 'Annonce boostée avec succès.',
            'data' => [
                'is_boosted' => true,
                'boost_expires_at' => $ad->fresh()?->boost_expires_at,
            ],
        ]);
    }

    /**
     * Remove boost from an ad.
     */
    public function unboost(Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $ad->unboost();

        return response()->json(['message' => 'Boost supprimé.']);
    }
}
