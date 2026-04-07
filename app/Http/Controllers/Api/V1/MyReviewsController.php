<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MyReviewsController
{
    /**
     * List all reviews for ads owned by the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/my/reviews",
     *     summary="Avis reçus sur mes annonces",
     *     description="Retourne tous les avis laissés sur les annonces de l'utilisateur authentifié.",
     *     tags={"⭐ Avis"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Liste paginée des avis reçus"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(): AnonymousResourceCollection
    {
        $reviews = Review::query()
            ->whereHas('ad', fn ($q) => $q->where('user_id', auth()->id()))
            ->with(['user.agency', 'ad', 'ad.media', 'ad.ad_type', 'ad.quarter.city'])
            ->latest()
            ->paginate(max(1, min(100, (int) request('per_page', 15))));

        return ReviewResource::collection($reviews);
    }
}
