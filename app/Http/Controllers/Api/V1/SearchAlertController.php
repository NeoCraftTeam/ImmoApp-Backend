<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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

    public function store(StoreSearchAlertRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = $request->user();

        if ($user->searchAlerts()->where('is_active', true)->count() >= 10) {
            return response()->json(['message' => 'Limite de 10 alertes actives atteinte.'], 422);
        }

        $alert = $user->searchAlerts()->create($data);

        return response()->json(new JsonResource($alert), 201);
    }

    public function update(UpdateSearchAlertRequest $request, SearchAlert $searchAlert): JsonResponse
    {
        $this->authorizeAlert($request, $searchAlert);

        $data = $request->validated();

        $searchAlert->update($data);

        return response()->json(new JsonResource($searchAlert));
    }

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
     */
    public function previewCount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['nullable', 'uuid'],
            'city_name' => ['nullable', 'string', 'max:100'],
            'type_id' => ['nullable', 'uuid'],
            'quarter_id' => ['nullable', 'uuid'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'bedrooms_min' => ['nullable', 'integer', 'min:0'],
            'surface_min' => ['nullable', 'integer', 'min:0'],
            'has_parking' => ['nullable', 'boolean'],
        ]);

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
