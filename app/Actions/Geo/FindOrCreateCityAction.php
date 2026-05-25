<?php

declare(strict_types=1);

namespace App\Actions\Geo;

use App\Models\City;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cherche une ville par nom (insensible à la casse).
 * Si absente, la crée et enrichit avec les coordonnées GPS via Nominatim.
 */
final class FindOrCreateCityAction
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * @param  array{name: string, country?: string|null}  $data
     */
    public function handle(array $data): City
    {
        $name = trim($data['name']);
        $country = isset($data['country']) ? trim((string) $data['country']) : null;

        // 1. Recherche insensible à la casse
        $existing = City::query()
            ->where('name', 'ilike', $name)
            ->when($country, fn ($q) => $q->where('country', $country))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // 2. Geocoding Nominatim pour lat/lng
        $coords = $this->geocode($name, $country);

        // 3. Création
        return City::create([
            'name' => $name,
            'country' => $country,
            'latitude' => $coords['lat'] ?? null,
            'longitude' => $coords['lng'] ?? null,
        ]);
    }

    /** @return array{lat:float,lng:float}|array{} */
    private function geocode(string $city, ?string $country): array
    {
        try {
            $q = $country ? "{$city}, {$country}" : $city;
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'KeyHome/1.0 (contact@keyhome.app)'])
                ->get(self::NOMINATIM_URL, [
                    'q' => $q,
                    'format' => 'json',
                    'limit' => 1,
                    'featuretype' => 'city',
                ]);

            $results = $response->ok() ? $response->json() : [];

            if (!empty($results)) {
                return ['lat' => (float) $results[0]['lat'], 'lng' => (float) $results[0]['lon']];
            }
        } catch (\Throwable $e) {
            Log::warning("FindOrCreateCityAction geocode: {$e->getMessage()}");
        }

        return [];
    }
}
