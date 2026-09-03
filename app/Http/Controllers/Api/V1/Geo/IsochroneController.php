<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Geo;

use App\Http\Requests\Api\V1\Geo\IsochroneRequest;
use App\Services\Geo\IsochroneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
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
    public function __invoke(IsochroneRequest $request, IsochroneService $service): JsonResponse
    {
        $validated = $request->validated();

        $result = $service->get(
            lat: (float) $validated['lat'],
            lng: (float) $validated['lng'],
            profile: $validated['profile'] ?? 'foot-walking',
            rangeMinutes: (int) ($validated['range'] ?? 15),
        );

        if ($result === null) {
            // Naming the missing credential in the response would tell any caller
            // which provider and which env var the API runs on. The gap is an
            // operator concern: it goes to the log, not to the user.
            if (!$service->isConfigured()) {
                Log::warning('geo.ors.not_configured', ['endpoint' => 'isochrones']);

                return response()->json(
                    ['message' => "Le calcul de zones de trajet n'est pas disponible sur cette plateforme."],
                    503,
                );
            }

            return response()->json(
                ['message' => 'Service de calcul de zones temporairement indisponible. Veuillez réessayer dans quelques instants.'],
                503,
            );
        }

        return response()->json(['data' => $result]);
    }
}
