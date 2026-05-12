<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Enums\PropertyAttribute;
use App\Http\Requests\AdRequest;
use App\Http\Resources\AdResource as AdApiResource;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\City;
use App\Models\Quarter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles search, autocomplete, and facet endpoints for ads.
 *
 * CRUD operations → AdController
 * Geo proximity → AdGeoController
 * Status management → AdStatusController
 */
final readonly class AdSearchController
{
    public function __construct(private LoggerInterface $log) {}

    /**
     * Rechercher des annonces avec filtres multiples.
     *
     * @OA\Get(
     *     path="/api/v1/ads/search",
     *     summary="Rechercher des annonces",
     *     description="Rechercher des annonces avec filtres multiples : texte, ville, type, chambres, prix, surface, parking",
     *     operationId="rechercherAnnonces",
     *     tags={"🔍 Filtre"},
     *
     *     @OA\Parameter(name="q", in="query", description="Terme de recherche textuel", required=false, @OA\Schema(type="string", example="appartement lumineux")),
     *     @OA\Parameter(name="city", in="query", description="Filtrer par ville", required=false, @OA\Schema(type="string", example="Paris")),
     *     @OA\Parameter(name="sort", in="query", description="Champ de tri", required=false, @OA\Schema(type="string", enum={"price", "surface_area", "created_at"}, example="price")),
     *     @OA\Parameter(name="order", in="query", description="Ordre de tri", required=false, @OA\Schema(type="string", enum={"asc", "desc"}, example="asc")),
     *     @OA\Parameter(name="per_page", in="query", description="Nombre d'éléments par page", required=false, @OA\Schema(type="integer", example=15)),
     *
     *     @OA\Response(response=200, description="Résultats de recherche",
     *
     *         @OA\JsonContent(type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdResource")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function search(AdRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            if (config('scout.driver') !== 'meilisearch') {
                return $this->searchFallback($validated);
            }

            $q = (string) ($validated['q'] ?? '');
            $city = $validated['city'] ?? null;
            $type = $validated['type'] ?? null;
            $typeId = $validated['type_id'] ?? null;
            $quarterId = $validated['quarter_id'] ?? null;
            $quarterName = $validated['quarter'] ?? null;
            $transactionType = $validated['transaction_type'] ?? null;

            $minBedrooms = isset($validated['bedrooms']) ? (int) $validated['bedrooms'] : null;
            $minBathrooms = isset($validated['bathrooms']) ? (int) $validated['bathrooms'] : null;
            $minPrice = isset($validated['price_min']) ? (float) $validated['price_min'] : null;
            $maxPrice = isset($validated['price_max']) ? (float) $validated['price_max'] : null;
            $minSurface = isset($validated['surface_min']) ? (float) $validated['surface_min'] : null;
            $maxSurface = isset($validated['surface_max']) ? (float) $validated['surface_max'] : null;
            $hasParking = isset($validated['has_parking']) ? (bool) $validated['has_parking'] : null;
            $has3dTour = isset($validated['has_3d_tour']) ? (bool) $validated['has_3d_tour'] : null;
            $isVerified = isset($validated['is_verified']) ? (bool) $validated['is_verified'] : null;
            $amenities = isset($validated['attributes']) && is_array($validated['attributes'])
                ? array_filter(array_map(strval(...), $validated['attributes']))
                : [];

            $sortBy = $validated['sort'] ?? 'created_at';
            $sortOrder = strtolower($validated['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $perPage = min(max((int) ($validated['per_page'] ?? config('pagination.per_page', 15)), 1), 100);

            $filters = [];

            if (!empty($city)) {
                $filters[] = sprintf("city = '%s'", str_replace("'", "\\'", $city));
            }
            if (!empty($type)) {
                $filters[] = sprintf("type = '%s'", str_replace("'", "\\'", $type));
            }
            if (!empty($typeId)) {
                $filters[] = sprintf("type_id = '%s'", str_replace("'", "\\'", (string) $typeId));
            }
            if (!empty($quarterId)) {
                $filters[] = sprintf("quarter_id = '%s'", str_replace("'", "\\'", (string) $quarterId));
            }
            if (!empty($quarterName)) {
                $filters[] = sprintf("quarter = '%s'", str_replace("'", "\\'", $quarterName));
            }
            if (!empty($transactionType)) {
                $filters[] = sprintf("transaction_type = '%s'", $transactionType);
            }
            if ($minBedrooms !== null) {
                $filters[] = sprintf('bedrooms >= %d', $minBedrooms);
            }
            if ($minBathrooms !== null) {
                $filters[] = sprintf('bathrooms >= %d', $minBathrooms);
            }
            if ($minPrice !== null) {
                $filters[] = sprintf('price >= %f', $minPrice);
            }
            if ($maxPrice !== null) {
                $filters[] = sprintf('price <= %f', $maxPrice);
            }
            if ($minSurface !== null) {
                $filters[] = sprintf('surface_area >= %f', $minSurface);
            }
            if ($maxSurface !== null) {
                $filters[] = sprintf('surface_area <= %f', $maxSurface);
            }
            if ($hasParking !== null) {
                $filters[] = sprintf('has_parking = %s', $hasParking ? 'true' : 'false');
            }
            if ($has3dTour !== null) {
                $filters[] = sprintf('has_3d_tour = %s', $has3dTour ? 'true' : 'false');
            }
            if ($isVerified !== null) {
                $filters[] = sprintf('is_verified = %s', $isVerified ? 'true' : 'false');
            }
            foreach ($amenities as $amenity) {
                if ($amenity === PropertyAttribute::Furnished->value) {
                    $filters[] = "(attributes = 'furnished' OR is_furnished = true)";
                } else {
                    $filters[] = sprintf("attributes = '%s'", str_replace("'", "\\'", $amenity));
                }
            }

            $filters[] = "status = 'available'";
            $filters[] = 'is_visible = true';

            $allowedSorts = ['price', 'surface_area', 'created_at', 'boost_score', 'reviews_avg_rating', 'views_count'];

            $latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
            $longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : null;

            $builder = Ad::search($q, function (Indexes $index, string $query, array $options) use ($filters, $sortBy, $sortOrder, $allowedSorts, $latitude, $longitude) {
                $options['filter'] = implode(' AND ', $filters);

                if ($sortBy === '_geoPoint' && $latitude !== null && $longitude !== null) {
                    // Geo-priority queries don't apply the boost premium —
                    // the user explicitly asked for proximity ordering.
                    $options['sort'] = [sprintf('_geoPoint(%f, %f):%s', $latitude, $longitude, $sortOrder)];
                } elseif (!in_array($sortBy, $allowedSorts, true)) {
                    $options['sort'] = ['boost_score:desc', 'created_at:desc'];
                } elseif ($sortBy === 'boost_score') {
                    // Already a boost-explicit sort — fall through to created_at tie-break.
                    $options['sort'] = [sprintf('%s:%s', $sortBy, $sortOrder), 'created_at:desc'];
                } else {
                    // Boost premium: every standard sort lifts active boosted ads to
                    // the top first, then secondary sorts within each tier.
                    $options['sort'] = ['boost_score:desc', sprintf('%s:%s', $sortBy, $sortOrder)];
                }

                return $index->search($query, $options);
            })
                ->query(fn ($eloquent) => $eloquent->with(['quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'agency'])->withAvg('reviews', 'rating')->withCount('reviews'));

            $results = $builder->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => AdApiResource::collection($results->items()),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                ],
                'links' => [
                    'first' => $results->url(1),
                    'last' => $results->url($results->lastPage()),
                    'prev' => $results->previousPageUrl(),
                    'next' => $results->nextPageUrl(),
                ],
            ], 200);
        } catch (ApiException|\Exception $e) {
            $this->log->warning('Search fallback to Eloquent: '.$e->getMessage());

            return $this->searchFallback($validated);
        }
    }

    /**
     * Fallback Eloquent search when Meilisearch is unavailable.
     *
     * @param  array<string, mixed>  $validated
     */
    private function searchFallback(array $validated): JsonResponse
    {
        $q = (string) ($validated['q'] ?? '');
        $city = $validated['city'] ?? null;
        $type = $validated['type'] ?? null;
        $typeId = $validated['type_id'] ?? null;
        $quarterId = $validated['quarter_id'] ?? null;
        $quarterName = $validated['quarter'] ?? null;
        $transactionType = $validated['transaction_type'] ?? null;
        $perPage = min(max((int) ($validated['per_page'] ?? config('pagination.per_page', 15)), 1), 100);
        $minBedrooms = isset($validated['bedrooms']) ? (int) $validated['bedrooms'] : null;
        $minBathrooms = isset($validated['bathrooms']) ? (int) $validated['bathrooms'] : null;
        $minPrice = isset($validated['price_min']) ? (float) $validated['price_min'] : null;
        $maxPrice = isset($validated['price_max']) ? (float) $validated['price_max'] : null;
        $minSurface = isset($validated['surface_min']) ? (float) $validated['surface_min'] : null;
        $maxSurface = isset($validated['surface_max']) ? (float) $validated['surface_max'] : null;
        $hasParking = isset($validated['has_parking']) ? (bool) $validated['has_parking'] : null;
        $has3dTour = isset($validated['has_3d_tour']) ? (bool) $validated['has_3d_tour'] : null;
        $isVerified = isset($validated['is_verified']) ? (bool) $validated['is_verified'] : null;
        $amenities = isset($validated['attributes']) && is_array($validated['attributes'])
            ? array_filter(array_map(strval(...), $validated['attributes']))
            : [];

        $query = Ad::query()
            ->with(['quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'agency'])
            ->visible()
            ->publiclyListed();

        if ($q) {
            $query->where(function ($qb) use ($q): void {
                $qb->where('title', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%")
                    ->orWhere('adresse', 'ilike', "%{$q}%");
            });
        }

        if ($typeId) {
            $query->where('type_id', $typeId);
        } elseif ($type) {
            $query->whereHas('ad_type', fn ($qb) => $qb->where('name', 'ilike', "%{$type}%"));
        }

        if ($quarterId) {
            $query->where('quarter_id', $quarterId);
        } elseif ($quarterName) {
            $query->whereHas('quarter', fn ($qb) => $qb->where('name', 'ilike', $quarterName));
        } elseif ($city) {
            $query->whereHas('quarter.city', fn ($qb) => $qb->where('name', 'ilike', "%{$city}%"));
        }

        if ($minBedrooms !== null) {
            $query->where('bedrooms', '>=', $minBedrooms);
        }

        if ($minBathrooms !== null) {
            $query->where('bathrooms', '>=', $minBathrooms);
        }

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        if ($minSurface !== null) {
            $query->where('surface_area', '>=', $minSurface);
        }

        if ($maxSurface !== null) {
            $query->where('surface_area', '<=', $maxSurface);
        }

        $sortBy = $validated['sort'] ?? 'created_at';
        $sortOrder = strtolower($validated['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($hasParking) {
            $query->where('has_parking', true);
        }

        if ($has3dTour) {
            $query->where('has_3d_tour', true);
        }

        if ($isVerified) {
            $query->where('is_verified', true);
        }

        if ($transactionType) {
            $query->where('transaction_type', $transactionType);
        }

        foreach ($amenities as $amenity) {
            if ($amenity === PropertyAttribute::Furnished->value) {
                $query->where(function ($qb): void {
                    $qb->whereJsonContains('attributes', PropertyAttribute::Furnished->value)
                        ->orWhereHas('ad_type', fn ($q) => $q->where('name', 'ilike', '%meubl%'));
                });
            } else {
                $query->whereJsonContains('attributes', $amenity);
            }
        }

        $allowedSorts = ['price', 'surface_area', 'created_at'];

        // Boost premium (parity with Meilisearch path): active boosted ads always
        // rise above non-boosted within the requested sort.
        $query->orderByDesc('boost_score');

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('created_at');
        }

        $results = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AdApiResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
            'links' => [
                'first' => $results->url(1),
                'last' => $results->url($results->lastPage()),
                'prev' => $results->previousPageUrl(),
                'next' => $results->nextPageUrl(),
            ],
        ], 200);
    }

    /**
     * Autocomplétion des champs de recherche.
     *
     * @OA\Get(
     *     path="/api/v1/ads/autocomplete",
     *     summary="Autocomplétion (villes, types, quartiers)",
     *     description="Retourne jusqu'à 10 suggestions commençant par le préfixe fourni.",
     *     operationId="autocompleteAnnonces",
     *     tags={"🔍 Filtre"},
     *
     *     @OA\Parameter(name="field", in="query", required=true, @OA\Schema(type="string", enum={"city", "type", "quarter"})),
     *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string", example="Pa")),
     *
     *     @OA\Response(response=200, description="Liste de suggestions"),
     *     @OA\Response(response=422, description="Paramètre 'field' invalide"),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function autocomplete(AdRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $field = $validated['field'] ?? null;
        $q = (string) ($validated['q'] ?? '');

        if (!in_array($field, ['city', 'type', 'quarter'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid field. Allowed values: city, type, quarter.',
            ], 422);
        }

        try {
            $driver = DB::getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';
            $prefix = $q !== '' ? ($q.'%') : '%';

            $publicStatuses = array_map(fn (AdStatus $s) => $s->value, Ad::PUBLIC_STATUSES);

            if ($field === 'city') {
                $rows = City::query()
                    ->join('quarter', 'quarter.city_id', '=', 'city.id')
                    ->join('ad', function ($join) use ($publicStatuses): void {
                        $join->on('ad.quarter_id', '=', 'quarter.id')
                            ->whereIn('ad.status', $publicStatuses);
                    })
                    ->when($q !== '', fn ($query) => $query->where('city.name', $likeOperator, $prefix))
                    ->groupBy('city.name')
                    ->selectRaw('city.name as value, COUNT(ad.id) as count')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get();
            } elseif ($field === 'type') {
                $rows = AdType::query()
                    ->join('ad', function ($join) use ($publicStatuses): void {
                        $join->on('ad.type_id', '=', 'ad_type.id')
                            ->whereIn('ad.status', $publicStatuses);
                    })
                    ->when($q !== '', fn ($query) => $query->where('ad_type.name', $likeOperator, $prefix))
                    ->groupBy('ad_type.name')
                    ->selectRaw('ad_type.name as value, COUNT(ad.id) as count')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get();
            } else {
                $rows = Quarter::query()
                    ->join('ad', function ($join) use ($publicStatuses): void {
                        $join->on('ad.quarter_id', '=', 'quarter.id')
                            ->whereIn('ad.status', $publicStatuses);
                    })
                    ->when($q !== '', fn ($query) => $query->where('quarter.name', $likeOperator, $prefix))
                    ->groupBy('quarter.name')
                    ->selectRaw('quarter.name as value, COUNT(ad.id) as count')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (Throwable $e) {
            $this->log->error('Autocomplete error', [
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtenir les facettes (compteurs) pour les filtres.
     *
     * @OA\Get(
     *     path="/api/v1/ads/facets",
     *     summary="Obtenir les facettes (compteurs de filtres)",
     *     description="Retourne les compteurs agrégés pour alimenter les filtres.",
     *     operationId="facettesAnnonces",
     *     tags={"🔍 Filtre"},
     *
     *     @OA\Response(response=200, description="Facettes récupérées avec succès"),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function facets(): JsonResponse
    {
        try {
            $driver = DB::getDriverName();
            $bedroomsCast = match ($driver) {
                'pgsql' => 'CAST(bedrooms as integer)',
                'sqlite' => 'CAST(bedrooms as integer)',
                default => 'CAST(bedrooms as signed)',
            };

            $publicStatuses = array_map(fn (AdStatus $s) => $s->value, Ad::PUBLIC_STATUSES);

            $cities = Ad::query()
                ->join('quarter', 'ad.quarter_id', '=', 'quarter.id')
                ->join('city', 'quarter.city_id', '=', 'city.id')
                ->whereIn('ad.status', $publicStatuses)
                ->whereNotNull('city.name')
                ->groupBy('city.name')
                ->selectRaw('city.name as name, COUNT(*) as count')
                ->orderByDesc('count')
                ->limit(20)
                ->get();

            $types = Ad::query()
                ->join('ad_type', 'ad.type_id', '=', 'ad_type.id')
                ->whereIn('ad.status', $publicStatuses)
                ->whereNotNull('ad_type.name')
                ->groupBy('ad_type.name')
                ->selectRaw('ad_type.name as name, COUNT(*) as count')
                ->orderByDesc('count')
                ->limit(20)
                ->get();

            $bedrooms = Ad::query()
                ->whereIn('status', $publicStatuses)
                ->whereNotNull('bedrooms')
                ->groupBy('bedrooms')
                ->selectRaw($bedroomsCast.' as value, COUNT(*) as count')
                ->orderBy('value')
                ->get();

            /** @var object{min: string|null, max: string|null}|null $priceRange */
            $priceRange = Ad::query()
                ->whereIn('status', $publicStatuses)
                ->whereNotNull('price')
                ->selectRaw('MIN(price) as min, MAX(price) as max')
                ->first();

            /** @var object{min: string|null, max: string|null}|null $surfaceRange */
            $surfaceRange = Ad::query()
                ->whereIn('status', $publicStatuses)
                ->whereNotNull('surface_area')
                ->selectRaw('MIN(surface_area) as min, MAX(surface_area) as max')
                ->first();

            $withParking = Ad::query()
                ->whereIn('status', $publicStatuses)
                ->where('has_parking', true)
                ->count();

            $withoutParking = Ad::query()
                ->whereIn('status', $publicStatuses)
                ->where('has_parking', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'cities' => $cities,
                    'types' => $types,
                    'bedrooms' => $bedrooms,
                    'price_range' => [
                        'min' => $priceRange?->min,
                        'max' => $priceRange?->max,
                    ],
                    'surface_range' => [
                        'min' => $surfaceRange?->min,
                        'max' => $surfaceRange?->max,
                    ],
                    'has_parking' => [
                        'with_parking' => $withParking,
                        'without_parking' => $withoutParking,
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            $this->log->error('Facets error', [
                'exception' => $e->getMessage(),
                'driver' => DB::getDriverName(),
            ]);
            throw $e;
        }
    }
}
