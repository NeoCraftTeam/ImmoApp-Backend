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
     */
    public function index(): AnonymousResourceCollection
    {
        $reviews = Review::query()
            ->whereHas('ad', fn ($q) => $q->where('user_id', auth()->id()))
            ->with(['user.agency', 'ad'])
            ->latest()
            ->paginate(max(1, min(100, (int) request('per_page', 15))));

        return ReviewResource::collection($reviews);
    }
}
