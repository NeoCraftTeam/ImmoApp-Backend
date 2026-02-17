<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Models\AdInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Annotations as OA;

/**
 * Handles user interactions with ads: views, favorites.
 *
 * These interactions feed the RecommendationEngine.
 */
final class AdInteractionController
{
    /**
     * Track an ad view.
     *
     * Debounced: only 1 view per user per ad every 5 minutes.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/view",
     *     summary="Track an ad view",
     *     tags={"📊 Interactions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=204, description="View tracked"),
     *     @OA\Response(response=429, description="View already tracked recently")
     * )
     */
    public function trackView(Request $request, Ad $ad): JsonResponse
    {
        $user = $request->user();

        // Debounce: 1 view per 5 minutes per user per ad
        $recentView = AdInteraction::where('user_id', $user->id)
            ->where('ad_id', $ad->id)
            ->where('type', AdInteraction::TYPE_VIEW)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($recentView) {
            return response()->json(null, 204);
        }

        AdInteraction::create([
            'user_id' => $user->id,
            'ad_id' => $ad->id,
            'type' => AdInteraction::TYPE_VIEW,
            'created_at' => now(),
        ]);

        return response()->json(null, 204);
    }

    /**
     * Track an ad impression (appeared in feed/list).
     *
     * Debounced: 1 impression per user per ad every 30 seconds.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/impression",
     *     summary="Track an ad impression",
     *     tags={"📊 Interactions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=204, description="Impression tracked")
     * )
     */
    public function trackImpression(Request $request, Ad $ad): JsonResponse
    {
        $user = $request->user();

        $recent = AdInteraction::where('user_id', $user->id)
            ->where('ad_id', $ad->id)
            ->where('type', AdInteraction::TYPE_IMPRESSION)
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();

        if (!$recent) {
            AdInteraction::create([
                'user_id' => $user->id,
                'ad_id' => $ad->id,
                'type' => AdInteraction::TYPE_IMPRESSION,
                'created_at' => now(),
            ]);
        }

        return response()->json(null, 204);
    }

    /**
     * Track an ad share.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/share",
     *     summary="Track an ad share",
     *     tags={"📊 Interactions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=204, description="Share tracked")
     * )
     */
    public function trackShare(Request $request, Ad $ad): JsonResponse
    {
        AdInteraction::create([
            'user_id' => $request->user()->id,
            'ad_id' => $ad->id,
            'type' => AdInteraction::TYPE_SHARE,
            'created_at' => now(),
        ]);

        return response()->json(null, 204);
    }

    /**
     * Track a contact button click.
     *
     * Debounced: 1 per user per ad per minute.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/contact-click",
     *     summary="Track a contact button click",
     *     tags={"📊 Interactions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=204, description="Contact click tracked")
     * )
     */
    public function trackContactClick(Request $request, Ad $ad): JsonResponse
    {
        return $this->trackDebouncedInteraction($request, $ad, AdInteraction::TYPE_CONTACT_CLICK, 60);
    }

    /**
     * Track a phone number click.
     *
     * Debounced: 1 per user per ad per minute.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/phone-click",
     *     summary="Track a phone number click",
     *     tags={"📊 Interactions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=204, description="Phone click tracked")
     * )
     */
    public function trackPhoneClick(Request $request, Ad $ad): JsonResponse
    {
        return $this->trackDebouncedInteraction($request, $ad, AdInteraction::TYPE_PHONE_CLICK, 60);
    }

    /**
     * Generic debounced interaction tracker.
     */
    private function trackDebouncedInteraction(Request $request, Ad $ad, string $type, int $debounceSeconds): JsonResponse
    {
        $user = $request->user();

        $recent = AdInteraction::where('user_id', $user->id)
            ->where('ad_id', $ad->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subSeconds($debounceSeconds))
            ->exists();

        if (!$recent) {
            AdInteraction::create([
                'user_id' => $user->id,
                'ad_id' => $ad->id,
                'type' => $type,
                'created_at' => now(),
            ]);
        }

        return response()->json(null, 204);
    }

    /**
     * Toggle favorite on an ad.
     *
     * Uses a simple check: count of favorites minus unfavorites.
     * Even count = not favorited, odd count = favorited.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/favorite",
     *     summary="Toggle favorite on an ad",
     *     tags={"📊 Interactions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Favorite toggled",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="is_favorited", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function toggleFavorite(Request $request, Ad $ad): JsonResponse
    {
        $user = $request->user();

        // Determine current state: favorites vs unfavorites count
        $favorites = AdInteraction::where('user_id', $user->id)
            ->where('ad_id', $ad->id)
            ->where('type', AdInteraction::TYPE_FAVORITE)
            ->count();

        $unfavorites = AdInteraction::where('user_id', $user->id)
            ->where('ad_id', $ad->id)
            ->where('type', AdInteraction::TYPE_UNFAVORITE)
            ->count();

        $isFavorited = $favorites > $unfavorites;

        // Create the toggle interaction
        AdInteraction::create([
            'user_id' => $user->id,
            'ad_id' => $ad->id,
            'type' => $isFavorited ? AdInteraction::TYPE_UNFAVORITE : AdInteraction::TYPE_FAVORITE,
            'created_at' => now(),
        ]);

        return response()->json([
            'is_favorited' => !$isFavorited,
            'message' => $isFavorited ? 'Retiré des favoris' : 'Ajouté aux favoris',
        ]);
    }

    /**
     * List the authenticated user's favorite ads.
     *
     * An ad is favorited if the number of favorite interactions
     * exceeds the number of unfavorite interactions for that ad.
     *
     * @OA\Get(
     *     path="/api/v1/my/favorites",
     *     summary="List my favorite ads",
     *     tags={"📊 Interactions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="List of favorite ads",
     *
     *         @OA\JsonContent(type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdResource"))
     *         )
     *     )
     * )
     */
    public function favorites(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        // Use a single query with GROUP BY to determine favorite state, avoiding N+1
        $favoritedAdIds = AdInteraction::where('user_id', $user->id)
            ->whereIn('type', [AdInteraction::TYPE_FAVORITE, AdInteraction::TYPE_UNFAVORITE])
            ->whereNotNull('ad_id')
            ->selectRaw('ad_id')
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as fav_count', [AdInteraction::TYPE_FAVORITE])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as unfav_count', [AdInteraction::TYPE_UNFAVORITE])
            ->groupBy('ad_id')
            ->havingRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) > SUM(CASE WHEN type = ? THEN 1 ELSE 0 END)', [
                AdInteraction::TYPE_FAVORITE,
                AdInteraction::TYPE_UNFAVORITE,
            ])
            ->pluck('ad_id');

        $ads = Ad::with(['quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'agency'])
            ->whereIn('id', $favoritedAdIds)
            ->where('status', AdStatus::AVAILABLE)
            ->latest()
            ->paginate(15);

        return AdResource::collection($ads);
    }
}
