<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Models\Payment;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Illuminate\Database\Eloquent\Builder;

class RecommendationController
{
    /**
     * Obtenir des recommandations d'annonces basées sur l'historique de l'utilisateur.
     *
     * @OA\Get(
     *     path="/api/v1/recommendations",
     *     summary="Recommandations personnalisées",
     *     description="Retourne une liste d'annonces recommandées en fonction des précédents déblocages (paiements) de l'utilisateur.
     *
     *     Fonctionnement :
     *     1. L'API identifie qui appelle ($user->id).
     *     2. Elle récupère uniquement les paiements de ce client.
     *     3. Elle calcule SES préférences (le type d'appartement qu'IL aime, la ville où IL cherche, SON budget moyen).
     *     4. Elle cherche des annonces qui correspondent à SES critères.
     *
     *     Exemples concrets :
     *     - Paul, qui a débloqué 2 appartements à Douala à 200.000 FCFA, verra des Appartements à Douala (~200k).
     *     - Marie, qui a débloqué des villas à Yaoundé à 1.000.000 FCFA, verra des Villas à Yaoundé (~1M).
     *     - Nouveau Client, qui n'a rien fait, verra les annonces les plus récentes (Cold Start).",
     *     tags={"🤖 Recommandations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des annonces recommandées",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdResource")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="source", type="string", example="personalized", description="Indique si les recommandations sont 'personalized' ou 'latest' (cold start)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Récupérer l'historique des interactions (paiements)
        // On regarde les 5 derniers déblocages pour avoir une tendance récente
        $lastInteractions = Payment::where('user_id', $user->id)
            ->where('status', 'success') // Assumons que le statut succès est 'success'
            ->with(['ad.quarter', 'ad'])
            ->latest()
            ->take(5)
            ->get();

        // 2. Cold Start : Pas d'historique ? -> Annonces récentes
        if ($lastInteractions->isEmpty()) {
            $latestAds = Ad::with(['quarter.city', 'ad_type', 'media', 'user'])
                ->where('status', 'available') // Seules les dispos
                ->latest()
                ->take(10)
                ->get();

            return AdResource::collection($latestAds)->additional(['meta' => ['source' => 'latest']]);
        }

        // 3. Analyse des préférences
        // On extrait les ID des annonces déjà vues pour les exclure
        $seenAdIds = $lastInteractions->pluck('ad_id')->toArray();

        // On récupère les catégories (types) préférées
        $preferredTypeIds = $lastInteractions->pluck('ad.type_id')->unique()->toArray();

        // On récupère les villes préférées (via quarter -> city)
        // Attention: ad->quarter peut être null si données incomplètes, d'où le filter
        $preferredCityIds = $lastInteractions->pluck('ad.quarter.city_id')->filter()->unique()->toArray();

        // Calcul du prix moyen (Budget estimé)
        $avgPrice = $lastInteractions->avg('ad.price');
        $minPrice = $avgPrice * 0.7; // -30%
        $maxPrice = $avgPrice * 1.3; // +30%

        // 4. Recherche des recommandations (Content-Based Filtering)
        $recommendations = Ad::with(['quarter.city', 'ad_type', 'media', 'user'])
            ->whereNotIn('id', $seenAdIds) // Exclure celles déjà payées
            ->where('status', 'available')
            ->where(function (Builder $query) use ($preferredTypeIds, $preferredCityIds, $minPrice, $maxPrice) {
                // Logique de scoring simplifiée :
                // On cherche des annonces qui matchent SOIT le type, SOIT la ville, AVEC le budget
                $query->where(function ($q) use ($preferredTypeIds, $minPrice, $maxPrice) {
                    $q->whereIn('type_id', $preferredTypeIds)
                        ->whereBetween('price', [$minPrice, $maxPrice]);
                })
                    ->orWhereHas('quarter', function ($q) use ($preferredCityIds) {
                    $q->whereIn('city_id', $preferredCityIds);
                });
            })
            ->inRandomOrder() // Mélanger un peu pour la diversité
            ->take(10)
            ->get();

        // Fallback : Si l'algo restrictif ne trouve rien (trop spécifique), on renvoie des annonces du même type sans filtre de prix
        if ($recommendations->isEmpty()) {
            $recommendations = Ad::with(['quarter.city', 'ad_type', 'media', 'user'])
                ->whereNotIn('id', $seenAdIds)
                ->where('status', 'available')
                ->whereIn('type_id', $preferredTypeIds)
                ->latest()
                ->take(10)
                ->get();
        }

        return AdResource::collection($recommendations)->additional(['meta' => ['source' => 'personalized']]);
    }
}
