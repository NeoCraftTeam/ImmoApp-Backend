<?php

declare(strict_types=1);

namespace App\Actions\Geo;

use App\Actions\Geo\Concerns\InteractsWithNominatim;
use App\Models\Quarter;
use Clickbar\Magellan\Data\Geometries\Point;

/**
 * Cherche un quartier par nom + city_id (insensible à la casse).
 * Si absent, le crée et enrichit avec les coordonnées GPS via Nominatim.
 */
final class FindOrCreateQuarterAction
{
    use InteractsWithNominatim;

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

        // 3. Création avec décimal + Point Magellan
        $lat = $coords['lat'] ?? null;
        $lng = $coords['lng'] ?? null;

        return Quarter::create([
            'name' => $name,
            'city_id' => $cityId,
            'latitude' => $lat,
            'longitude' => $lng,
            'location' => ($lat !== null && $lng !== null)
                ? Point::makeGeodetic($lat, $lng)
                : null,
        ]);
    }

    /** @return array{lat:float,lng:float}|array{} */
    private function geocode(string $quarter, ?string $city, ?string $country): array
    {
        $parts = array_filter([$quarter, $city, $country]);
        $results = $this->nominatimSearch(implode(', ', $parts));

        if ($results === []) {
            return [];
        }

        return ['lat' => (float) $results[0]['lat'], 'lng' => (float) $results[0]['lon']];
    }
}
