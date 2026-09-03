<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\PreviewSearchAlertCountRequest;
use App\Http\Requests\Api\V1\StoreSearchAlertRequest;
use App\Http\Requests\Api\V1\UpdateSearchAlertRequest;
use App\Models\Ad;
use App\Models\SearchAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

final class SearchAlertController
{
    /**
     * @OA\Get(
     *     path="/api/v1/search-alerts",
     *     summary="Lister mes alertes de recherche",
     *     tags={"🔔 Alertes"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Liste des alertes"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $alerts = $request->user()
            ->searchAlerts()
            ->orderByDesc('created_at')
            ->get();

        return JsonResource::collection($alerts);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/search-alerts",
     *     summary="Créer une alerte de recherche",
     *     description="Crée une nouvelle alerte. Un utilisateur peut avoir au maximum 10 alertes actives.",
     *     operationId="storeSearchAlert",
     *     tags={"🔔 Alertes"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="city_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="city_name", type="string", nullable=true),
     *             @OA\Property(property="type_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="quarter_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="price_min", type="integer", nullable=true),
     *             @OA\Property(property="price_max", type="integer", nullable=true),
     *             @OA\Property(property="bedrooms_min", type="integer", nullable=true),
     *             @OA\Property(property="surface_min", type="integer", nullable=true),
     *             @OA\Property(property="has_parking", type="boolean", nullable=true),
     *             @OA\Property(property="frequency", type="string", enum={"instant","daily","weekly"}, default="daily"),
     *             @OA\Property(property="is_active", type="boolean", default=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Alerte créée"),
     *     @OA\Response(response=422, description="Limite de 10 alertes atteinte ou données invalides"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function store(StoreSearchAlertRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = $request->user();

        // Dedup: if an identical alert (same search criteria) already exists,
        // return it instead of creating a near-duplicate the user would then have
        // to manage twice. Only the search-defining fields are compared — not the
        // label / notification settings / frequency.
        $criteriaKeys = [
            'city_id', 'city_name', 'type_id', 'type_name', 'quarter_id',
            'price_min', 'price_max', 'bedrooms_min', 'surface_min', 'has_parking', 'query',
        ];

        $existing = $user->searchAlerts()
            ->where(function ($query) use ($data, $criteriaKeys): void {
                foreach ($criteriaKeys as $key) {
                    $value = $data[$key] ?? null;
                    if ($value === null) {
                        $query->whereNull($key);
                    } else {
                        $query->where($key, $value);
                    }
                }
            })
            ->first();

        if ($existing !== null) {
            return response()->json(new JsonResource($existing), 200);
        }

        if ($user->searchAlerts()->where('is_active', true)->count() >= 10) {
            return response()->json(['message' => 'Limite de 10 alertes actives atteinte.'], 422);
        }

        $alert = $user->searchAlerts()->create($data);

        return response()->json(new JsonResource($alert), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/search-alerts/{searchAlert}",
     *     summary="Modifier une alerte",
     *     description="Met à jour les critères ou la fréquence d'une alerte de recherche.",
     *     operationId="updateSearchAlert",
     *     tags={"🔔 Alertes"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="searchAlert", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="is_active", type="boolean"),
     *         @OA\Property(property="frequency", type="string", enum={"instant","daily","weekly"}),
     *         @OA\Property(property="price_min", type="integer", nullable=true),
     *         @OA\Property(property="price_max", type="integer", nullable=true)
     *     )),
     *
     *     @OA\Response(response=200, description="Alerte mise à jour"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=404, description="Alerte introuvable")
     * )
     */
    public function update(UpdateSearchAlertRequest $request, SearchAlert $searchAlert): JsonResponse
    {
        $this->authorizeAlert($request, $searchAlert);

        $data = $request->validated();

        $searchAlert->update($data);

        return response()->json(new JsonResource($searchAlert));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/search-alerts/{searchAlert}",
     *     summary="Supprimer une alerte",
     *     operationId="destroySearchAlert",
     *     tags={"🔔 Alertes"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="searchAlert", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Alerte supprimée"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function destroy(Request $request, SearchAlert $searchAlert): JsonResponse
    {
        $this->authorizeAlert($request, $searchAlert);
        $searchAlert->delete();

        return response()->json(['message' => 'Alerte supprimée.']);
    }

    /**
     * Returns the count of active ads that match the given alert criteria.
     * Used by the frontend to show "X annonces correspondent" at creation time.
     *
     * POST /search-alerts/preview-count
     *
     * @OA\Post(
     *     path="/api/v1/search-alerts/preview-count",
     *     summary="Compter les annonces correspondant à des critères d'alerte",
     *     description="Retourne le nombre d'annonces actives correspondant aux critères fournis. Utilisé pour afficher 'X annonces correspondent' au moment de la création.",
     *     operationId="searchAlertPreviewCount",
     *     tags={"🔔 Alertes"},
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="city_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="city_name", type="string", nullable=true),
     *             @OA\Property(property="type_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="quarter_id", type="string", format="uuid", nullable=true),
     *             @OA\Property(property="price_min", type="integer", nullable=true),
     *             @OA\Property(property="price_max", type="integer", nullable=true),
     *             @OA\Property(property="bedrooms_min", type="integer", nullable=true),
     *             @OA\Property(property="surface_min", type="integer", nullable=true),
     *             @OA\Property(property="has_parking", type="boolean", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Nombre d'annonces correspondantes", @OA\JsonContent(
     *
     *         @OA\Property(property="count", type="integer", example=42)
     *     ))
     * )
     */
    public function previewCount(PreviewSearchAlertCountRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Ad::query()->visible()->whereIn('status', Ad::PUBLIC_STATUSES);

        if (!empty($validated['city_id'])) {
            $query->whereHas('quarter.city', fn ($q) => $q->where('id', $validated['city_id']));
        } elseif (!empty($validated['city_name'])) {
            $query->whereHas('quarter.city', fn ($q) => $q->where('name', 'ilike', $validated['city_name']));
        }

        if (!empty($validated['type_id'])) {
            $query->where('type_id', $validated['type_id']);
        }

        if (!empty($validated['quarter_id'])) {
            $query->where('quarter_id', $validated['quarter_id']);
        }

        if (!empty($validated['price_min'])) {
            $query->where('price', '>=', $validated['price_min']);
        }

        if (!empty($validated['price_max'])) {
            $query->where('price', '<=', $validated['price_max']);
        }

        if (!empty($validated['bedrooms_min'])) {
            $query->where('bedrooms', '>=', $validated['bedrooms_min']);
        }

        if (!empty($validated['surface_min'])) {
            $query->where('surface_area', '>=', $validated['surface_min']);
        }

        if (isset($validated['has_parking']) && $validated['has_parking']) {
            $query->where('has_parking', true);
        }

        return response()->json(['count' => $query->count()]);
    }

    private function authorizeAlert(Request $request, SearchAlert $alert): void
    {
        abort_unless($alert->user_id === $request->user()->id, 403);
    }
}
