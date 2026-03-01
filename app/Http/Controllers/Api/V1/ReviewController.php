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
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="⭐ Avis", description="Gestion des avis sur les annonces")
 */
final class ReviewController
{
    /**
     * List reviews for a given ad (public).
     *
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/reviews",
     *     summary="Lister les avis d'une annonce",
     *     description="Retourne les avis paginés pour une annonce donnée, triés par date décroissante.",
     *     tags={"⭐ Avis"},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, description="UUID de l'annonce", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des avis",
     *
     *         @OA\JsonContent(type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ReviewResource"))
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Annonce introuvable")
     * )
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
     *
     * @OA\Post(
     *     path="/api/v1/reviews",
     *     summary="Ajouter un avis",
     *     description="Crée un avis pour une annonce. Un seul avis par utilisateur et par annonce.",
     *     tags={"⭐ Avis"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"rating", "ad_id"},
     *
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=4),
     *             @OA\Property(property="comment", type="string", maxLength=1000, example="Très bon logement, propre et bien situé."),
     *             @OA\Property(property="ad_id", type="string", format="uuid")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Avis créé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Avis ajouté avec succès."),
     *             @OA\Property(property="data", ref="#/components/schemas/ReviewResource")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Validation échouée ou avis déjà déposé")
     * )
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
