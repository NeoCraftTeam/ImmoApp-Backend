<?php

declare(strict_types=1);

namespace App\Actions\Geo;

use App\Actions\Geo\Concerns\InteractsWithNominatim;
use App\Models\City;
use Clickbar\Magellan\Data\Geometries\Point;

/**
 * Cherche une ville par nom (insensible à la casse).
 * Si absente, valide via Nominatim avant de créer.
 * Lance InvalidArgumentException si la ville est introuvable sur OSM.
 */
final class FindOrCreateCityAction
{
    use InteractsWithNominatim;

    /**
     * @param  array{name: string, country?: string|null}  $data
     *
     * @throws \InvalidArgumentException si introuvable sur OpenStreetMap
     */
    public function handle(array $data): City
    {
        $name = trim($data['name']);
        $country = isset($data['country']) ? trim($data['country']) : null;

        // 1. Déjà en base → retourner directement
        $existing = City::query()
            ->where('name', 'ilike', $name)
            ->when($country, fn ($q) => $q->where('country', 'ilike', $country))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // 2. Validation Nominatim — refuse les noms fantaisistes (aucune restriction géographique)
        $geo = $this->nominatimValidate($name, $country);

        if ($geo === null) {
            throw new \InvalidArgumentException(
                "Ville introuvable : \u00ab {$name} \u00bb n'est pas reconnue sur OpenStreetMap."
            );
        }

        // 3. Créer avec le nom canonique OSM + GPS (décimal + Point Magellan) + pays extrait de Nominatim
        return City::create([
            'name' => $geo['canonical'],
            'country' => $geo['country'] ?? $country,
            'latitude' => $geo['lat'],
            'longitude' => $geo['lng'],
            'location' => Point::makeGeodetic($geo['lat'], $geo['lng']),
        ]);
    }

    /**
     * Appelle Nominatim et retourne nom canonique + coords + pays si trouvé, null sinon.
     * Fonctionne pour toute ville dans le monde (aucune restriction géographique).
     *
     * @return array{canonical:string,lat:float,lng:float,country:string|null}|null
     */
    private function nominatimValidate(string $city, ?string $country): ?array
    {
        $q = $country ? "{$city}, {$country}" : $city;
        $results = $this->nominatimSearch($q, ['addressdetails' => 1]);

        if ($results === []) {
            return null;
        }

        $hit = $results[0];
        $address = $hit['address'] ?? [];

        return [
            'canonical' => (string) ($hit['name'] ?? $address['city'] ?? $address['town'] ?? $address['village'] ?? $city),
            'lat' => (float) $hit['lat'],
            'lng' => (float) $hit['lon'],
            'country' => $address['country'] ?? null,
        ];
    }
}
