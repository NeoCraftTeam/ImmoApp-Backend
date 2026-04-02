<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreSearchAlertRequest;
use App\Http\Requests\Api\V1\UpdateSearchAlertRequest;
use App\Models\SearchAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

final class SearchAlertController
{
    /**
     * @OA\Get(
     *     path="/api/v1/my/search-alerts",
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

    private function authorizeAlert(Request $request, SearchAlert $alert): void
    {
        abort_unless($alert->user_id === $request->user()->id, 403);
    }
}
