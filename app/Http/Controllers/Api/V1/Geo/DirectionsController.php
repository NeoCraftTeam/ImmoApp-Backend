<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Geo;

use App\Http\Requests\Api\V1\Geo\DirectionsRequest;
use App\Services\Geo\DirectionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
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
    public function __invoke(DirectionsRequest $request, DirectionsService $service): JsonResponse
    {
        $validated = $request->validated();

        $result = $service->get(
            fromLat: (float) $validated['from_lat'],
            fromLng: (float) $validated['from_lng'],
            toLat: (float) $validated['to_lat'],
            toLng: (float) $validated['to_lng'],
            profile: $validated['profile'] ?? 'driving-car',
        );

        if ($result === null) {
            // Naming the missing credential in the response would tell any caller
            // which provider and which env var the API runs on. The gap is an
            // operator concern: it goes to the log, not to the user.
            if (!$service->isConfigured()) {
                Log::warning('geo.ors.not_configured', ['endpoint' => 'directions']);

                return response()->json(
                    ['message' => "Le calcul d'itinéraire n'est pas disponible sur cette plateforme."],
                    503,
                );
            }

            return response()->json(
                ['message' => "Calcul d'itinéraire temporairement indisponible. Veuillez réessayer dans quelques instants."],
                503,
            );
        }

        return response()->json(['data' => $result]);
    }
}
