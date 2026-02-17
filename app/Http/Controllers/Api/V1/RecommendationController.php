<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AdResource;
use App\Services\RecommendationEngine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Annotations as OA;

/**
 * Recommendations endpoint — delegates to RecommendationEngine.
 */
final class RecommendationController
{
    /**
     * Obtenir des recommandations d'annonces personnalisées.
     *
     * L'algorithme utilise un système de scoring pondéré :
     * - Type de bien (×40) : correspond aux types préférés de l'utilisateur
     * - Ville (×25) : proximité géographique avec les recherches passées
     * - Budget (×20) : courbe gaussienne autour du budget moyen
     * - Fraîcheur (×10) : les annonces récentes sont favorisées
     * - Popularité (×5) : basée sur le nombre de vues
     *
     * 20% des résultats sont des annonces "exploratoires" hors profil
     * pour éviter les bulles de filtres.
     *
     * Cold Start : Sans historique, retourne un mix trending + boosted + latest.
     *
     * @OA\Get(
     *     path="/api/v1/recommendations",
     *     summary="Recommandations personnalisées (scoring pondéré v2)",
     *     description="Retourne une liste d'annonces recommandées basée sur un algorithme de scoring pondéré.
     *
     *     Signaux utilisés : vues, favoris, déblocages (paiements).
     *     Pondération : type (40%) + ville (25%) + budget (20%) + fraîcheur (10%) + popularité (5%).
     *     Diversité : 20% d'annonces exploratoires hors profil.
     *     Cold Start : trending + boosted + latest.",
     *     tags={"⭐ Recommandations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste des annonces recommandées",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdResource")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="source", type="string", example="personalized"),
     *                 @OA\Property(property="algorithm", type="string", example="weighted_scoring_v2"),
     *                 @OA\Property(property="profile_signals", type="integer", example=5),
     *                 @OA\Property(property="candidates_scored", type="integer", example=42),
     *                 @OA\Property(property="diversity_injected", type="integer", example=3)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $engine = new RecommendationEngine;
        $result = $engine->recommend($user);

        return AdResource::collection($result['ads'])
            ->additional(['meta' => $result['meta']]);
    }
}
