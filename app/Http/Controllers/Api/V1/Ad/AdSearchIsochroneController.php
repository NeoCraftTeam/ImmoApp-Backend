<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ad;

use App\Http\Requests\Api\V1\Ad\AdSearchIsochroneRequest;
use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Services\Geo\IsochroneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Builder;
use Meilisearch\Endpoints\Indexes;
use OpenApi\Attributes as OA;

/**
 * Returns all active ads reachable within X minutes from a given point.
 *
 * Steps:
 *   1. Fetch isochrone polygon from ORS (or cache)
 *   2. Extract exterior ring coordinates from the GeoJSON FeatureCollection
 *   3. Build a Meilisearch _geoPolygon() filter string
 *   4. Run Ad::search() with that filter and return results + polygon
 *
 * POST /api/v1/search/isochrone
 */
#[OA\Post(
    path: '/api/v1/search/isochrone',
    summary: 'Annonces dans un rayon de trajet',
    description: 'Retourne les annonces accessibles en moins de N minutes depuis un point. Utilise ORS pour le calcul isochrone et Meilisearch _geoPolygon pour le filtrage.',
    tags: ['🗺️ Géo'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['lat', 'lng'],
            properties: [
                new OA\Property(property: 'lat', type: 'number', description: 'Latitude du point de départ'),
                new OA\Property(property: 'lng', type: 'number', description: 'Longitude du point de départ'),
                new OA\Property(property: 'max_minutes', type: 'integer', minimum: 5, maximum: 60, default: 15),
                new OA\Property(property: 'mode', type: 'string', enum: ['foot-walking', 'driving-car', 'cycling-regular'], default: 'driving-car'),
                new OA\Property(property: 'per_page', type: 'integer', minimum: 1, maximum: 100, default: 30),
                new OA\Property(property: 'transaction_type', type: 'string', enum: ['location', 'vente']),
                new OA\Property(property: 'type_id', type: 'string'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Annonces + polygone GeoJSON'),
        new OA\Response(response: 422, description: 'Paramètres invalides'),
        new OA\Response(response: 503, description: 'ORS non configuré ou indisponible'),
    ]
)]
final readonly class AdSearchIsochroneController
{
    public function __construct(private IsochroneService $isochroneService) {}

    public function __invoke(AdSearchIsochroneRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $maxMinutes = (int) ($validated['max_minutes'] ?? 15);
        $profile = $validated['mode'] ?? 'driving-car';
        $perPage = (int) ($validated['per_page'] ?? 30);

        $result = $this->isochroneService->get($lat, $lng, $profile, $maxMinutes);

        if ($result === null) {
            // Naming the missing credential in the response would tell any caller
            // which provider and which env var the API runs on. The gap is an
            // operator concern: it goes to the log, not to the user.
            if (!$this->isochroneService->isConfigured()) {
                Log::warning('geo.ors.not_configured', ['endpoint' => 'search/isochrone']);

                return response()->json(
                    ['message' => "La recherche par temps de trajet n'est pas disponible sur cette plateforme."],
                    503,
                );
            }

            return response()->json(
                ['message' => 'Service de calcul de zones temporairement indisponible. Veuillez réessayer dans quelques instants.'],
                503,
            );
        }

        // Extract the polygon coordinates and build a Meilisearch _geoPolygon filter.
        // ORS returns GeoJSON with coordinates as [lng, lat]; Meilisearch expects [lat, lng].
        $polygonFilter = $this->buildGeoPolygonFilter($result['geojson']);

        if ($polygonFilter === null) {
            return response()->json(
                ['message' => 'Impossible d\'extraire le polygone isochrone.'],
                422,
            );
        }

        // Build Meilisearch filter array
        $publicStatuses = array_map(fn ($s) => $s->value, Ad::PUBLIC_STATUSES);
        $filters = ['status IN ['.implode(', ', array_map(fn ($s) => '"'.$s.'"', $publicStatuses)).']'];

        if (!empty($validated['transaction_type'])) {
            $filters[] = 'transaction_type = "'.$validated['transaction_type'].'"';
        }

        if (!empty($validated['type_id'])) {
            $filters[] = 'type_id = "'.$validated['type_id'].'"';
        }

        // Combine geo polygon filter with other filters
        $filterString = implode(' AND ', $filters).' AND '.$polygonFilter;

        /** @var Builder $builder */
        $builder = Ad::search('', function (Indexes $index, string $query, array $options) use ($filterString) {
            $options['filter'] = $filterString;
            $options['sort'] = ['desc(relevance_score)'];
            $options['attributesToRetrieve'] = ['id'];

            return $index->search($query, $options);
        });

        $ads = $builder->paginate($perPage);

        // Load full ad models for the resource
        $adIds = collect($ads->items())->pluck('id');
        $fullAds = Ad::with(['quarter.city', 'type', 'media'])
            ->whereIn('id', $adIds)
            ->get()
            ->keyBy('id');

        // Preserve Meilisearch ranking order
        $ordered = $adIds->map(fn ($id) => $fullAds->get($id))->filter();

        return response()->json([
            'data' => AdResource::collection($ordered),
            'total' => $ads->total(),
            'polygon' => $result['geojson'],
            'profile' => $result['profile'],
            'range_minutes' => $result['range_minutes'],
            'center' => $result['center'],
            'cached' => $result['cached'],
        ]);
    }

    /**
     * Build a Meilisearch _geoPolygon() filter string from an ORS isochrone GeoJSON FeatureCollection.
     *
     * ORS coordinates: [[lng, lat], ...]
     * Meilisearch:     _geoPolygon([lat1, lng1], [lat2, lng2], ...)
     */
    private function buildGeoPolygonFilter(array $geojson): ?string
    {
        // Traverse: FeatureCollection → features[0] → geometry → coordinates[0]
        $features = $geojson['features'] ?? [];

        if (empty($features)) {
            return null;
        }

        $geometry = $features[0]['geometry'] ?? null;

        if ($geometry === null) {
            return null;
        }

        $type = $geometry['type'] ?? '';

        // Polygon exterior ring is coordinates[0]; MultiPolygon is coordinates[0][0]
        $ring = match ($type) {
            'Polygon' => $geometry['coordinates'][0] ?? null,
            'MultiPolygon' => $geometry['coordinates'][0][0] ?? null,
            default => null,
        };

        if (empty($ring)) {
            return null;
        }

        // ORS returns [lng, lat]; flip to [lat, lng] for Meilisearch
        $points = array_map(
            fn (array $coord) => sprintf('[%f, %f]', (float) $coord[1], (float) $coord[0]),
            $ring,
        );

        return '_geoPolygon('.implode(', ', $points).')';
    }
}
