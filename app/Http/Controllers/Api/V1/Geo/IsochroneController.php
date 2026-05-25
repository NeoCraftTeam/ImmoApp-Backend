<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Geo;

use App\Services\Geo\IsochroneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/isochrones',
    summary: 'Zone accessible depuis un point',
    description: 'Retourne un polygone GeoJSON (isochrone) représentant la zone atteignable en X minutes depuis les coordonnées données. Nécessite ORS_API_KEY. Mis en cache 24 h.',
    tags: ['🗺️ Géo'],
    parameters: [
        new OA\Parameter(name: 'lat', in: 'query', required: true, schema: new OA\Schema(type: 'number')),
        new OA\Parameter(name: 'lng', in: 'query', required: true, schema: new OA\Schema(type: 'number')),
        new OA\Parameter(name: 'profile', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['foot-walking', 'driving-car', 'cycling-regular', 'wheelchair'], default: 'foot-walking')),
        new OA\Parameter(name: 'range', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 5, maximum: 60, default: 15)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Isochrone GeoJSON'),
        new OA\Response(response: 422, description: 'Paramètres invalides'),
        new OA\Response(response: 503, description: 'ORS non configuré ou indisponible'),
    ]
)]
final class IsochroneController
{
    public function __invoke(Request $request, IsochroneService $service): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'profile' => ['sometimes', 'string', 'in:'.implode(',', IsochroneService::PROFILES)],
            'range' => ['sometimes', 'integer', 'min:5', 'max:60'],
        ]);

        $result = $service->get(
            lat: (float) $validated['lat'],
            lng: (float) $validated['lng'],
            profile: $validated['profile'] ?? 'foot-walking',
            rangeMinutes: (int) ($validated['range'] ?? 15),
        );

        if ($result === null) {
            return response()->json(
                ['message' => 'Service de calcul de zones non disponible. Vérifiez que ORS_API_KEY est configuré.'],
                503,
            );
        }

        return response()->json(['data' => $result]);
    }
}
