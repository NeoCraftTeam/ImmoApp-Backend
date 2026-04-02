<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\TestimonialResource;
use App\Models\Ad;
use App\Models\City;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Public landing-page statistics — extracted from inline closure routes (W37).
 */
final class StatsController
{
    /**
     * @OA\Get(
     *     path="/api/v1/stats/landing",
     *     tags={"📊 Statistics"},
     *     summary="Landing-page aggregate stats",
     *
     *     @OA\Response(response=200, description="Counts for ads, cities, and users")
     * )
     */
    public function landing(): JsonResponse
    {
        return response()->json([
            'ads_count' => Ad::query()->publiclyListed()->where('is_visible', true)->count(),
            'cities_count' => City::query()->count(),
            'users_count' => User::query()->count(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stats/testimonials",
     *     tags={"📊 Statistics"},
     *     summary="Top testimonials for landing page",
     *
     *     @OA\Response(response=200, description="Recent high-rating reviews + aggregate rating")
     * )
     */
    public function testimonials(): JsonResponse
    {
        $reviews = Review::query()
            ->whereNotNull('comment')
            ->where('rating', '>=', 4)
            ->with(['user.city'])
            ->latest()
            ->limit(8)
            ->get();

        $averageRating = round(
            (float) (Review::query()->avg('rating') ?? 4.6),
            1
        );
        $totalCount = Review::query()->count();

        return response()->json([
            'data' => TestimonialResource::collection($reviews),
            'meta' => [
                'average_rating' => $averageRating,
                'total_count' => $totalCount,
            ],
        ]);
    }
}
