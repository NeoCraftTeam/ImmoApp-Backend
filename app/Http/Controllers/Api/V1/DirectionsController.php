<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\DirectionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/directions',
    summary: 'Calcul d\'itinéraire entre deux points',
    description: 'Retourne un itinéraire GeoJSON avec résumé (distance, durée) entre deux coordonnées GPS. Profiles : voiture, pied, vélo, fauteuil roulant. Nécessite ORS_API_KEY. Mis en cache 24 h.',
    tags: ['🗺️ Géo'],
    parameters: [
        new OA\Parameter(name: 'from_lat', in: 'query', required: true, schema: new OA\Schema(type: 'number')),
        new OA\Parameter(name: 'from_lng', in: 'query', required: true, schema: new OA\Schema(type: 'number')),
        new OA\Parameter(name: 'to_lat', in: 'query', required: true, schema: new OA\Schema(type: 'number')),
        new OA\Parameter(name: 'to_lng', in: 'query', required: true, schema: new OA\Schema(type: 'number')),
        new OA\Parameter(name: 'profile', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['foot-walking', 'driving-car', 'cycling-regular', 'wheelchair'], default: 'driving-car')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Itinéraire GeoJSON + résumé'),
        new OA\Response(response: 422, description: 'Paramètres invalides'),
        new OA\Response(response: 503, description: 'ORS non configuré ou indisponible'),
    ]
)]
final class DirectionsController
{
    public function __invoke(Request $request, DirectionsService $service): JsonResponse
    {
        $validated = $request->validate([
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],
            'to_lat' => ['required', 'numeric', 'between:-90,90'],
            'to_lng' => ['required', 'numeric', 'between:-180,180'],
            'profile' => ['sometimes', 'string', 'in:'.implode(',', DirectionsService::PROFILES)],
        ]);

        $result = $service->get(
            fromLat: (float) $validated['from_lat'],
            fromLng: (float) $validated['from_lng'],
            toLat: (float) $validated['to_lat'],
            toLng: (float) $validated['to_lng'],
            profile: $validated['profile'] ?? 'driving-car',
        );

        if ($result === null) {
            return response()->json(
                ['message' => 'Calcul d\'itinéraire non disponible. Vérifiez que ORS_API_KEY est configuré.'],
                503,
            );
        }

        return response()->json(['data' => $result]);
    }
}
