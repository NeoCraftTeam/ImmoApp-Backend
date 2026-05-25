<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Services\Geo\NeighborhoodScorecardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/ads/{ad}/neighborhood-scorecard',
    summary: 'Scorecard de quartier',
    description: 'Retourne les scores basés sur des nœuds OpenStreetMap réels. Distances piétonnes via OpenRouteService lorsque ORS_API_KEY est défini (en-tête Authorization Bearer). Sans clé : distances orthodromiques (ligne droite, status=ok). status=degraded si ORS est configuré mais indisponible. status=unavailable si Overpass échoue.',
    tags: ['🏠 Annonces'],
    parameters: [
        new OA\Parameter(name: 'ad', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Scorecard calculé'),
        new OA\Response(response: 404, description: 'Annonce introuvable'),
        new OA\Response(response: 422, description: 'Annonce sans coordonnées GPS'),
    ]
)]
final class NeighborhoodScorecardController
{
    public function __invoke(Request $request, Ad $ad, NeighborhoodScorecardService $service): JsonResponse
    {
        if (!$ad->location) {
            return response()->json([
                'message' => "Cette annonce n'a pas de coordonnées GPS.",
            ], 422);
        }

        $scorecard = $service->compute(
            $ad->location->getLatitude(),
            $ad->location->getLongitude(),
            $request->boolean('force', false),
        );

        return response()->json(['data' => $scorecard]);
    }
}
