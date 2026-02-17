<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ReviewResource;
use App\Models\Ad;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

final class ReviewController
{
    /**
     * List reviews for a given ad (public).
     */
    public function index(Ad $ad): AnonymousResourceCollection
    {
        $reviews = $ad->reviews()
            ->with('user')
            ->latest()
            ->paginate(15);

        return ReviewResource::collection($reviews);
    }

    /**
     * Store a new review (authenticated customers only).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'ad_id' => ['required', 'exists:ad,id'],
        ]);

        // Prevent duplicate reviews: one review per user per ad
        $exists = Review::where('user_id', $user->id)
            ->where('ad_id', $validated['ad_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Vous avez déjà laissé un avis sur cette annonce.',
            ], 422);
        }

        $review = DB::transaction(fn () => Review::create([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'ad_id' => $validated['ad_id'],
            'user_id' => $user->id,
        ]));

        $review->load('user');

        return response()->json([
            'message' => 'Avis ajouté avec succès.',
            'data' => new ReviewResource($review),
        ], 201);
    }
}
