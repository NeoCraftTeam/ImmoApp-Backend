<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Quarter;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importe les villes et leurs quartiers depuis Overpass (OpenStreetMap).
 * Stratégie : Nominatim → bounding-box → Overpass → upsert BD.
 * Gratuit, sans clé API, coordonnées GPS incluses.
 *
 * Usage :
 *   php artisan geo:import-overpass
 *   php artisan geo:import-overpass --country="Cameroun"
 *   php artisan geo:import-overpass --city="Douala"
 *   php artisan geo:import-overpass --dry-run
 */
final class ImportGeoFromOverpass extends Command
{
    protected $signature = 'geo:import-overpass
                            {--country= : Limiter à un pays (ex: Cameroun)}
                            {--city=    : Limiter à une ville (ex: Douala)}
                            {--dry-run  : Afficher sans enregistrer}';

    protected $description = 'Importe villes + quartiers avec coordonnées GPS depuis Overpass/Nominatim (OpenStreetMap)';

    private const string NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /** Miroirs Overpass testés dans l'ordre */
    private const array OVERPASS_MIRRORS = [
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass-api.de/api/interpreter',
    ];

    private const string PLACE_TYPES = 'suburb|quarter|neighbourhood|village|hamlet';

    private const string UA = 'KeyHome/1.0 (contact@keyhome.app)';

    public function handle(): int
    {
        $countryFilter = $this->option('country');
        $cityFilter = $this->option('city');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Aucune donnée ne sera enregistrée.');
        }

        $cities = City::query()
            ->when($countryFilter, fn ($q) => $q->where('country', $countryFilter))
            ->when($cityFilter, fn ($q) => $q->where('name', $cityFilter))
            ->get();

        if ($cities->isEmpty()) {
            $this->error("Aucune ville trouvée. Lancez d'abord: php artisan db:seed --class=CityQuarterCameroonSeeder");

            return self::FAILURE;
        }

        $this->info("Import Overpass pour {$cities->count()} ville(s)...");
        $bar = $this->output->createProgressBar($cities->count());
        $bar->start();

        $imported = 0;
        $skipped = 0;

        foreach ($cities as $city) {
            try {
                // 1. Nominatim → centre + bounding box de la ville
                $geo = $this->nominatimLookup($city->name, $city->country);

                if (!$geo) {
                    $skipped++;
                    $bar->advance();
                    usleep(1_100_000);

                    continue;
                }

                // 2. Mise à jour coordonnées de la ville (décimal + Point Magellan)
                if (!$dryRun) {
                    $city->update([
                        'latitude' => $geo['lat'],
                        'longitude' => $geo['lng'],
                        'location' => Point::makeGeodetic($geo['lat'], $geo['lng']),
                        'country' => $city->country ?? ($geo['country'] ?? null),
                    ]);
                }

                // 3. Overpass dans la bbox
                $elements = $this->queryOverpass($geo['bbox']);

                foreach ($elements as $q) {
                    $imported++;
                    if (!$dryRun) {
                        Quarter::updateOrCreate(
                            ['name' => $q['name'], 'city_id' => $city->id],
                            [
                                'latitude' => $q['lat'],
                                'longitude' => $q['lng'],
                                'location' => Point::makeGeodetic($q['lat'], $q['lng']),
                            ],
                        );
                    } else {
                        $this->line("  [dry] {$city->name} / {$q['name']} ({$q['lat']}, {$q['lng']})");
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("ImportGeoFromOverpass: {$city->name} — {$e->getMessage()}");
            }

            $bar->advance();
            usleep(1_100_000); // rate-limit Nominatim + Overpass (1 req/s)
        }

        $bar->finish();
        $this->newLine();
        $this->info("Terminé — {$imported} quartiers importés/mis à jour, {$skipped} villes ignorées.");

        return self::SUCCESS;
    }

    /**
     * Requête Nominatim pour récupérer lat/lng + bbox + pays de la ville.
     *
     * @return array{lat:float,lng:float,bbox:array{float,float,float,float},country:string|null}|null
     */
    private function nominatimLookup(string $cityName, ?string $country): ?array
    {
        $params = [
            'q' => $country ? "{$cityName}, {$country}" : $cityName,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
        ];

        $response = Http::timeout(15)
            ->withHeaders(['User-Agent' => self::UA])
            ->get(self::NOMINATIM_URL, $params);

        if (!$response->ok()) {
            return null;
        }

        $results = $response->json();
        if (empty($results)) {
            return null;
        }

        $r = $results[0];
        $bb = $r['boundingbox'] ?? null; // [south, north, west, east]

        if (!$bb || count($bb) < 4) {
            return null;
        }

        $address = $r['address'] ?? [];

        return [
            'lat' => (float) $r['lat'],
            'lng' => (float) $r['lon'],
            'bbox' => [(float) $bb[0], (float) $bb[2], (float) $bb[1], (float) $bb[3]], // S,W,N,E
            'country' => $address['country'] ?? null,
        ];
    }

    /**
     * Requête Overpass dans la bounding box — essaie les miroirs dans l'ordre.
     *
     * @param  array{float,float,float,float}  $bbox  [south, west, north, east]
     * @return list<array{name:string,lat:float,lng:float}>
     */
    private function queryOverpass(array $bbox): array
    {
        [$s, $w, $n, $e] = $bbox;
        $types = self::PLACE_TYPES;

        $query = "[out:json][timeout:30];\n"
            ."(\n"
            ."  node[\"place\"~\"{$types}\"]({$s},{$w},{$n},{$e});\n"
            ."  way[\"place\"~\"{$types}\"]({$s},{$w},{$n},{$e});\n"
            ."  relation[\"place\"~\"{$types}\"]({$s},{$w},{$n},{$e});\n"
            .");\n"
            .'out center;';

        foreach (self::OVERPASS_MIRRORS as $url) {
            try {
                $response = Http::timeout(35)
                    ->withHeaders(['User-Agent' => self::UA])
                    ->asForm()
                    ->post($url, ['data' => $query]);

                if ($response->ok()) {
                    return $this->extractQuarters($response->json('elements', []));
                }
            } catch (\Throwable) {
                // essayer le miroir suivant
            }
        }

        return [];
    }

    /**
     * @param  list<array<string,mixed>>  $elements
     * @return list<array{name:string,lat:float,lng:float}>
     */
    private function extractQuarters(array $elements): array
    {
        $quarters = [];
        $seen = [];

        foreach ($elements as $el) {
            $name = trim((string) ($el['tags']['name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $lat = (float) ($el['lat'] ?? $el['center']['lat'] ?? 0);
            $lng = (float) ($el['lon'] ?? $el['center']['lon'] ?? 0);

            if ($lat === 0.0 && $lng === 0.0) {
                continue;
            }

            $quarters[] = ['name' => $name, 'lat' => $lat, 'lng' => $lng];
            $seen[$name] = true;
        }

        return $quarters;
    }
}
