<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\TestimonialResource;
use App\Models\Ad;
use App\Models\City;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

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
        // 30 min TTL — counts move slowly; cold-path COUNT(*) on Ads is the dominant cost.
        // Warm via scheduler (see Console\Kernel) so end-users never hit cold cache.
        $stats = Cache::remember('landing:stats', now()->addMinutes(30), fn () => [
            'ads_count' => Ad::query()->publiclyListed()->where('is_visible', true)->count(),
            'cities_count' => City::query()->count(),
            'users_count' => User::query()->count(),
        ]);

        return response()->json($stats);
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
        $data = Cache::remember('landing:testimonials', now()->addMinutes(10), function () {
            $reviews = Review::query()
                ->whereNotNull('comment')
                ->where('rating', '>=', 4)
                ->with(['user:id,firstname,lastname,role,city_id' => ['city:id,name']])
                ->latest()
                ->limit(8)
                ->get();

            return [
                'data' => TestimonialResource::collection($reviews),
                'meta' => [
                    'average_rating' => round((float) (Review::query()->avg('rating') ?? 4.6), 1),
                    'total_count' => Review::query()->count(),
                ],
            ];
        });

        return response()->json($data);
    }
}
