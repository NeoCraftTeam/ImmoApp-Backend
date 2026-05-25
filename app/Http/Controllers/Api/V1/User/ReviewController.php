<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Requests\Api\V1\RespondToReviewRequest;
use App\Http\Requests\Api\V1\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\Review;
use App\Models\UnlockedAd;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="⭐ Avis", description="Gestion des avis sur les annonces")
 */
final class ReviewController
{
    use AuthorizesRequests;

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
            ->with('user:id,firstname,lastname,avatar')
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
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $this->authorize('create', Review::class);

        $user = $request->user();

        $validated = $request->validated();

        // Prevent duplicate reviews: one review per user per ad
        $exists = Review::where('user_id', $user->id)
            ->where('ad_id', $validated['ad_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Vous avez déjà laissé un avis sur cette annonce.',
            ], 422);
        }

        // Trust check: user must have interacted with the ad (unlocked, contact click, or view)
        $hasInteraction = UnlockedAd::where('user_id', $user->id)
            ->where('ad_id', $validated['ad_id'])
            ->exists()
            || AdInteraction::where('user_id', $user->id)
                ->where('ad_id', $validated['ad_id'])
                ->whereIn('type', ['contact_click', 'phone_click', 'view'])
                ->exists();

        $review = DB::transaction(fn () => Review::create([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'ad_id' => $validated['ad_id'],
            'user_id' => $user->id,
            'is_verified' => $hasInteraction,
        ]));

        $review->load('user');

        return response()->json([
            'message' => 'Avis ajouté avec succès.',
            'data' => new ReviewResource($review),
        ], 201);
    }

    /**
     * Allow the ad owner to respond to a review.
     *
     * @OA\Post(
     *     path="/api/v1/reviews/{review}/respond",
     *     summary="Répondre à un avis",
     *     description="Permet au propriétaire de l'annonce de répondre à un avis.",
     *     tags={"⭐ Avis"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="review", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"response"},
     *
     *             @OA\Property(property="response", type="string", maxLength=1000, example="Merci pour votre avis !")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Réponse ajoutée"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=422, description="Réponse déjà donnée")
     * )
     */
    public function respond(RespondToReviewRequest $request, Review $review): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validated();

        return DB::transaction(function () use ($user, $review, $validated): JsonResponse {
            /** @var Review|null $locked */
            $locked = Review::query()->whereKey($review->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return response()->json([
                    'message' => 'Avis introuvable.',
                ], 404);
            }

            $ad = $locked->ad;
            if (!$ad || $ad->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Seul le propriétaire de l\'annonce peut répondre.',
                ], 403);
            }

            if ($locked->owner_response !== null) {
                return response()->json([
                    'message' => 'Vous avez déjà répondu à cet avis.',
                ], 422);
            }

            $locked->update([
                'owner_response' => $validated['response'],
                'owner_responded_at' => now(),
            ]);

            return response()->json([
                'message' => 'Réponse ajoutée avec succès.',
                'data' => new ReviewResource($locked->fresh('user')),
            ]);
        });
    }
}
