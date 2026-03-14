<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\SearchAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

final class SearchAlertController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $alerts = $request->user()
            ->searchAlerts()
            ->orderByDesc('created_at')
            ->get();

        return JsonResource::collection($alerts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'city_id' => ['nullable', 'uuid'],
            'city_name' => ['nullable', 'string', 'max:100'],
            'type_id' => ['nullable', 'uuid'],
            'type_name' => ['nullable', 'string', 'max:100'],
            'quarter_id' => ['nullable', 'uuid'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'bedrooms_min' => ['nullable', 'integer', 'min:0'],
            'surface_min' => ['nullable', 'integer', 'min:0'],
            'has_parking' => ['nullable', 'boolean'],
            'query' => ['nullable', 'string', 'max:200'],
        ]);

        $user = $request->user();

        if ($user->searchAlerts()->where('is_active', true)->count() >= 10) {
            return response()->json(['message' => 'Limite de 10 alertes actives atteinte.'], 422);
        }

        $alert = $user->searchAlerts()->create($data);

        return response()->json(new JsonResource($alert), 201);
    }

    public function update(Request $request, SearchAlert $searchAlert): JsonResponse
    {
        $this->authorizeAlert($request, $searchAlert);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'city_id' => ['nullable', 'uuid'],
            'city_name' => ['nullable', 'string', 'max:100'],
            'type_id' => ['nullable', 'uuid'],
            'type_name' => ['nullable', 'string', 'max:100'],
            'quarter_id' => ['nullable', 'uuid'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'bedrooms_min' => ['nullable', 'integer', 'min:0'],
            'surface_min' => ['nullable', 'integer', 'min:0'],
            'has_parking' => ['nullable', 'boolean'],
            'query' => ['nullable', 'string', 'max:200'],
            'is_active' => ['nullable', 'boolean'],
        ]);

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
