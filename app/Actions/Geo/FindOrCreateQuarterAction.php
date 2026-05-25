<?php

declare(strict_types=1);

namespace App\Actions\Geo;

use App\Models\Quarter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cherche un quartier par nom + city_id (insensible à la casse).
 * Si absent, le crée et enrichit avec les coordonnées GPS via Nominatim.
 */
final class FindOrCreateQuarterAction
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * @param  array{name: string, city_id: string, city_name?: string|null, country?: string|null}  $data
     */
    public function handle(array $data): Quarter
    {
        $name = trim($data['name']);
        $cityId = $data['city_id'];

        // 1. Recherche insensible à la casse
        $existing = Quarter::query()
            ->where('city_id', $cityId)
            ->where('name', 'ilike', $name)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // 2. Geocoding Nominatim (quartier + ville + pays pour plus de précision)
        $coords = $this->geocode($name, $data['city_name'] ?? null, $data['country'] ?? null);

        // 3. Création
        return Quarter::create([
            'name' => $name,
            'city_id' => $cityId,
            'latitude' => $coords['lat'] ?? null,
            'longitude' => $coords['lng'] ?? null,
        ]);
    }

    /** @return array{lat:float,lng:float}|array{} */
    private function geocode(string $quarter, ?string $city, ?string $country): array
    {
        try {
            $parts = array_filter([$quarter, $city, $country]);
            $q = implode(', ', $parts);

            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'KeyHome/1.0 (contact@keyhome.app)'])
                ->get(self::NOMINATIM_URL, [
                    'q' => $q,
                    'format' => 'json',
                    'limit' => 1,
                ]);

            $results = $response->ok() ? $response->json() : [];

            if (!empty($results)) {
                return ['lat' => (float) $results[0]['lat'], 'lng' => (float) $results[0]['lon']];
            }
        } catch (\Throwable $e) {
            Log::warning("FindOrCreateQuarterAction geocode: {$e->getMessage()}");
        }

        return [];
    }
}
