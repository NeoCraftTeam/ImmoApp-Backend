<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Geo;

use App\Actions\Geo\FindOrCreateCityAction;
use App\Actions\Geo\FindOrCreateQuarterAction;
use App\Http\Resources\CityResource;
use App\Http\Resources\QuarterResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Permet aux bailleurs de créer une ville ou un quartier manquant
 * lors de la saisie d'une annonce.
 * Geocoding GPS automatique via Nominatim.
 */
final class GeoFindOrCreateController extends Controller
{
    public function __construct(
        private readonly FindOrCreateCityAction $findOrCreateCity,
        private readonly FindOrCreateQuarterAction $findOrCreateQuarter,
    ) {}

    /**
     * POST /api/v1/geo/city
     * Trouve ou crée une ville par nom + pays.
     */
    public function city(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $city = $this->findOrCreateCity->handle($data);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Geo find-or-create rejected', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Impossible de créer ou trouver cette ville. Vérifiez les données saisies.'], 422);
        }

        Cache::forever('geo:catalog_version', (string) Str::uuid());

        return response()->json([
            'data' => new CityResource($city),
            'created' => $city->wasRecentlyCreated,
        ], $city->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * POST /api/v1/geo/quarter
     * Trouve ou crée un quartier dans une ville donnée.
     */
    public function quarter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'city_id' => ['required', 'uuid', 'exists:city,id'],
        ]);

        // Enrichit la requête avec le nom et pays de la ville pour le geocoding
        $city = City::find($data['city_id']);
        $data['city_name'] = $city?->name;
        $data['country'] = $city?->country;

        $quarter = $this->findOrCreateQuarter->handle($data);

        Cache::forever('geo:catalog_version', (string) Str::uuid());

        return response()->json([
            'data' => new QuarterResource($quarter),
            'created' => $quarter->wasRecentlyCreated,
        ], $quarter->wasRecentlyCreated ? 201 : 200);
    }
}
