<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Support\GeoNameNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class OsmPlaceSynchronizer
{
    /** @return array{cities:int,quarters:int,unmatched_quarters:int} */
    public function sync(?string $fallbackCountryCode = null): array
    {
        $cities = $this->syncCities($fallbackCountryCode);
        [$quarters, $unmatched] = $this->syncQuarters($fallbackCountryCode);

        Cache::forever('geo:catalog_version', (string) Str::uuid());

        return ['cities' => $cities, 'quarters' => $quarters, 'unmatched_quarters' => $unmatched];
    }

    private function syncCities(?string $fallbackCountryCode): int
    {
        $types = config('osm.city_place_types');
        $rows = DB::select($this->resolvedPlacesSql(count($types)), [
            $fallbackCountryCode,
            ...$types,
        ]);
        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            if ($row->country_code === null || $row->latitude === null || $row->longitude === null) {
                continue;
            }

            $payload[] = [
                'id' => (string) Str::orderedUuid(),
                'name' => $row->name,
                'display_name' => $row->display_name ?: $row->name,
                'normalized_name' => GeoNameNormalizer::normalize($row->name),
                'country' => $this->countryName($row->country_code),
                'country_code' => strtoupper($row->country_code),
                'admin_area' => $row->admin_area,
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'location' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $row->longitude, $row->latitude)),
                'osm_type' => $row->osm_type,
                'osm_id' => $row->osm_id,
                'place_type' => $row->place_type,
                'source' => 'openstreetmap',
                'osm_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($payload, 1_000) as $chunk) {
            DB::table('city')->upsert(
                $chunk,
                ['osm_type', 'osm_id'],
                ['name', 'display_name', 'normalized_name', 'country', 'country_code', 'admin_area', 'latitude', 'longitude', 'location', 'place_type', 'source', 'osm_updated_at', 'updated_at'],
            );
        }

        DB::statement(<<<'SQL'
            UPDATE city AS c
               SET boundary = ST_Multi(ST_CollectionExtract(p.boundary, 3))
              FROM osm_import.places AS p
             WHERE c.osm_type = p.osm_type
               AND c.osm_id = p.osm_id
               AND p.boundary IS NOT NULL
            SQL);

        return count($payload);
    }

    /** @return array{int,int} */
    private function syncQuarters(?string $fallbackCountryCode): array
    {
        $types = config('osm.quarter_place_types');
        $placeSql = $this->resolvedPlacesSql(count($types));
        $maxDistance = ((int) config('osm.quarter_max_city_distance_km', 75)) * 1_000;
        $sql = <<<SQL
            WITH resolved AS ({$placeSql})
            SELECT r.*,
                   contained.id AS contained_city_id,
                   nearest.id AS nearest_city_id,
                   nearest.distance_m
              FROM resolved AS r
              LEFT JOIN LATERAL (
                    SELECT c.id
                      FROM city AS c
                     WHERE c.country_code = r.country_code
                       AND c.boundary IS NOT NULL
                       AND ST_Covers(c.boundary, ST_SetSRID(ST_MakePoint(r.longitude, r.latitude), 4326))
                     ORDER BY ST_Area(c.boundary) ASC
                     LIMIT 1
              ) AS contained ON true
              LEFT JOIN LATERAL (
                    SELECT c.id,
                           ST_DistanceSphere(c.location, ST_SetSRID(ST_MakePoint(r.longitude, r.latitude), 4326)) AS distance_m
                      FROM city AS c
                     WHERE c.country_code = r.country_code
                       AND c.location IS NOT NULL
                     ORDER BY c.location <-> ST_SetSRID(ST_MakePoint(r.longitude, r.latitude), 4326)
                     LIMIT 1
              ) AS nearest ON true
            SQL;
        $rows = DB::select($sql, [$fallbackCountryCode, ...$types]);
        $now = now();
        $payload = [];
        $unmatched = 0;
        $unmatchedSamples = [];

        foreach ($rows as $row) {
            $cityId = $this->resolveParentCity($row, $maxDistance);

            if ($cityId === null) {
                $unmatched++;
                if (count($unmatchedSamples) < 25) {
                    $unmatchedSamples[] = $row->name;
                }

                continue;
            }

            $payload[] = [
                'id' => (string) Str::orderedUuid(),
                'name' => $row->name,
                'display_name' => $row->display_name ?: $row->name,
                'normalized_name' => GeoNameNormalizer::normalize($row->name),
                'city_id' => $cityId,
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'location' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $row->longitude, $row->latitude)),
                'osm_type' => $row->osm_type,
                'osm_id' => $row->osm_id,
                'place_type' => $row->place_type,
                'source' => 'openstreetmap',
                'osm_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($unmatched > 0) {
            Log::warning('geo.osm.quarters_unmatched', [
                'count' => $unmatched,
                'sample' => $unmatchedSamples,
            ]);
        }

        foreach (array_chunk($payload, 1_000) as $chunk) {
            DB::table('quarter')->upsert(
                $chunk,
                ['osm_type', 'osm_id'],
                ['name', 'display_name', 'normalized_name', 'city_id', 'latitude', 'longitude', 'location', 'place_type', 'source', 'osm_updated_at', 'updated_at'],
            );
        }

        DB::statement(<<<'SQL'
            UPDATE quarter AS q
               SET boundary = ST_Multi(ST_CollectionExtract(p.boundary, 3))
              FROM osm_import.places AS p
             WHERE q.osm_type = p.osm_type
               AND q.osm_id = p.osm_id
               AND p.boundary IS NOT NULL
            SQL);

        return [count($payload), $unmatched];
    }

    /**
     * Resolve a quarter's parent city. Prefers the city whose administrative
     * boundary geographically contains the quarter (no distance limit), then
     * falls back to the nearest city centroid within the configured cap.
     * Returns null when neither rule matches so the caller counts and logs the
     * orphan instead of silently discarding it.
     */
    private function resolveParentCity(object $row, int $maxDistance): ?string
    {
        if ($row->contained_city_id !== null) {
            return (string) $row->contained_city_id;
        }

        if (
            $row->nearest_city_id !== null
            && $row->distance_m !== null
            && (float) $row->distance_m <= $maxDistance
        ) {
            return (string) $row->nearest_city_id;
        }

        return null;
    }

    private function resolvedPlacesSql(int $typeCount): string
    {
        $placeholders = implode(', ', array_fill(0, $typeCount, '?'));

        return <<<SQL
            SELECT p.osm_type,
                   p.osm_id,
                   p.name,
                   p.display_name,
                   p.place_type,
                   COALESCE(upper(p.country_code), upper(country.country_code), upper(?)) AS country_code,
                   admin.name AS admin_area,
                   ST_Y(COALESCE(p.location, ST_PointOnSurface(p.boundary))) AS latitude,
                   ST_X(COALESCE(p.location, ST_PointOnSurface(p.boundary))) AS longitude
              FROM osm_import.places AS p
              LEFT JOIN LATERAL (
                    SELECT b.country_code
                      FROM osm_import.admin_boundaries AS b
                     WHERE b.admin_level = 2
                       AND b.country_code IS NOT NULL
                       AND ST_Covers(b.boundary, COALESCE(p.location, ST_PointOnSurface(p.boundary)))
                     LIMIT 1
              ) AS country ON true
              LEFT JOIN LATERAL (
                    SELECT b.name
                      FROM osm_import.admin_boundaries AS b
                     WHERE b.admin_level BETWEEN 3 AND 6
                       AND ST_Covers(b.boundary, COALESCE(p.location, ST_PointOnSurface(p.boundary)))
                     ORDER BY b.admin_level DESC
                     LIMIT 1
              ) AS admin ON true
             WHERE p.place_type IN ({$placeholders})
               AND COALESCE(p.location, p.boundary) IS NOT NULL
            SQL;
    }

    private function countryName(string $countryCode): string
    {
        $name = \Locale::getDisplayRegion('-'.strtoupper($countryCode), 'fr');

        return $name !== '' ? $name : strtoupper($countryCode);
    }
}
