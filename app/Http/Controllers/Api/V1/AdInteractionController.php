<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Models\AdInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
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

        if (!$user) {
            return response()->json(null, 204);
        }

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

        if (!$user) {
            return response()->json(null, 204);
        }

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

        /**
         * Wrapped in a transaction with a row-level lock to prevent the
         * read-then-write race condition where two concurrent requests
         * could both read the same favorite state before either writes.
         *
         * Canonical favorite-state logic lives here.
         *
         * @see Ad::isFavoritedBy() — mirrors this logic; keep them in sync.
         */
        $result = DB::transaction(function () use ($user, $ad): array {
            // Lock the user's interaction rows for this ad to serialise concurrent toggles.
            $interactions = AdInteraction::where('user_id', $user->id)
                ->where('ad_id', $ad->id)
                ->whereIn('type', [AdInteraction::TYPE_FAVORITE, AdInteraction::TYPE_UNFAVORITE])
                ->lockForUpdate()
                ->get();

            $favorites = $interactions->where('type', AdInteraction::TYPE_FAVORITE)->count();
            $unfavorites = $interactions->where('type', AdInteraction::TYPE_UNFAVORITE)->count();
            $isFavorited = $favorites > $unfavorites;

            AdInteraction::create([
                'user_id' => $user->id,
                'ad_id' => $ad->id,
                'type' => $isFavorited ? AdInteraction::TYPE_UNFAVORITE : AdInteraction::TYPE_FAVORITE,
                'created_at' => now(),
            ]);

            return [
                'is_favorited' => !$isFavorited,
                'message' => $isFavorited ? 'Retiré des favoris' : 'Ajouté aux favoris',
            ];
        });

        return response()->json($result);
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
    /**
     * Get the authenticated user's recently viewed ads.
     *
     * @OA\Get(
     *     path="/api/v1/my/recently-viewed",
     *     summary="Get recently viewed ads",
     *     tags={"📊 Interactions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="List of recently viewed ads",
     *
     *         @OA\JsonContent(type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdResource"))
     *         )
     *     )
     * )
     */
    public function recentlyViewed(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $viewedAdIds = AdInteraction::where('user_id', $user->id)
            ->where('type', AdInteraction::TYPE_VIEW)
            ->whereNotNull('ad_id')
            ->selectRaw('ad_id, MAX(created_at) as last_viewed')
            ->groupBy('ad_id')
            ->orderByDesc('last_viewed')
            ->limit(10)
            ->pluck('ad_id');

        $ads = Ad::with([
            'quarter:id,name,city_id',
            'quarter.city:id,name',
            'ad_type:id,name',
            'media',
            'user:id,firstname,lastname,avatar,agency_id,city_id',
            'user.agency:id,name,slug,logo',
            'agency:id,name,slug,logo',
        ])
            ->whereIn('id', $viewedAdIds)
            ->visible()
            ->publiclyListed()
            ->get()
            ->sortBy(fn ($ad) => array_search($ad->id, $viewedAdIds->toArray()))
            ->values();

        return AdResource::collection($ads);
    }

    public function favorites(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        /**
         * Canonical favorite-state logic lives in AdInteractionController::toggleFavorite().
         *
         * @see AdInteractionController::toggleFavorite() — source of truth for the fav/unfav counting logic.
         * @see Ad::isFavoritedBy() — model helper that also implements this logic; keep all three in sync.
         */
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

        if ($favoritedAdIds->isEmpty()) {
            return AdResource::collection(collect());
        }

        $ads = Ad::with([
            'quarter:id,name,city_id',
            'quarter.city:id,name',
            'ad_type:id,name',
            'media',
            'user:id,firstname,lastname,avatar,agency_id,city_id',
            'user.agency:id,name,slug,logo',
            'agency:id,name,slug,logo',
        ])
            ->whereIn('id', $favoritedAdIds)
            ->visible()
            ->publiclyListed()
            ->latest()
            ->paginate(15);

        return AdResource::collection($ads);
    }
}
