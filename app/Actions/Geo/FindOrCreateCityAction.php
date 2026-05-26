<?php

declare(strict_types=1);

namespace App\Actions\Geo;

use App\Models\City;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cherche une ville par nom (insensible à la casse).
 * Si absente, valide via Nominatim avant de créer.
 * Lance InvalidArgumentException si la ville est introuvable sur OSM.
 */
final class FindOrCreateCityAction
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * @param  array{name: string, country?: string|null}  $data
     *
     * @throws \InvalidArgumentException si introuvable sur OpenStreetMap
     */
    public function handle(array $data): City
    {
        $name = trim($data['name']);
        $country = isset($data['country']) ? trim((string) $data['country']) : null;

        // 1. Déjà en base → retourner directement
        $existing = City::query()
            ->where('name', 'ilike', $name)
            ->when($country, fn ($q) => $q->where('country', $country))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // 2. Validation Nominatim — refuse les noms fantaisistes
        $geo = $this->nominatimValidate($name, $country);

        if ($geo === null) {
            throw new \InvalidArgumentException(
                "Ville introuvable : \u00ab {$name} \u00bb n'est pas reconnue sur OpenStreetMap."
            );
        }

        // 3. Créer avec le nom canonique OSM + GPS
        return City::create([
            'name' => $geo['canonical'],
            'country' => $country,
            'latitude' => $geo['lat'],
            'longitude' => $geo['lng'],
        ]);
    }

    /**
     * Appelle Nominatim et retourne nom canonique + coords si trouvé, null sinon.
     *
     * @return array{canonical:string,lat:float,lng:float}|null
     */
    private function nominatimValidate(string $city, ?string $country): ?array
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
                return [
                    'canonical' => (string) ($results[0]['name'] ?? $city),
                    'lat' => (float) $results[0]['lat'],
                    'lng' => (float) $results[0]['lon'],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("FindOrCreateCityAction nominatim: {$e->getMessage()}");
        }

        return null;
    }
}
