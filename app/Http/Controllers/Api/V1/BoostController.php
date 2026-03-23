<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->whereNotNull('boost_score')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'price', 'boost_score', 'boost_duration_days', 'description']);

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
     * Duration defaults to 7 days; score to 50.
     */
    public function boost(Request $request, Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $durationDays = $validated['duration_days'] ?? 7;

        $ad->boost(50, $durationDays);

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
