<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Quarter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importe les villes et leurs quartiers depuis l'API Overpass (OpenStreetMap).
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

    protected $description = 'Importe villes + quartiers avec coordonnées GPS depuis Overpass API (OpenStreetMap)';

    private const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';

    private const PLACE_TYPES = 'suburb|quarter|neighbourhood|village|hamlet';

    /** @var array<string,string> Nom KeyHome => Nom OSM pour les grandes villes */
    private const CITY_OSM_MAP = [
        'Douala' => 'Douala',
        'Yaoundé' => 'Yaoundé',
        'Libreville' => 'Libreville',
        'Brazzaville' => 'Brazzaville',
        'Bangui' => 'Bangui',
        "N'Djamena" => "N'Djamena",
        'Malabo' => 'Malabo',
        'Abidjan' => 'Abidjan',
        'Dakar' => 'Dakar',
        'Bamako' => 'Bamako',
        'Ouagadougou' => 'Ouagadougou',
        'Lomé' => 'Lomé',
        'Cotonou' => 'Cotonou',
        'Niamey' => 'Niamey',
        'Bissau' => 'Bissau',
    ];

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
            $this->error('Aucune ville trouvée. Lancez d\'abord: php artisan db:seed --class=CityQuarterCameroonSeeder');

            return self::FAILURE;
        }

        $this->info("Import Overpass pour {$cities->count()} ville(s)...");
        $bar = $this->output->createProgressBar($cities->count());
        $bar->start();

        $imported = 0;
        $skipped = 0;

        foreach ($cities as $city) {
            $osmName = self::CITY_OSM_MAP[$city->name] ?? $city->name;

            try {
                $data = $this->queryOverpass($osmName);

                if (empty($data)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // Coordonnées du centre-ville (1er élément de type city/town/village)
                $cityCoords = $this->extractCityCoords($data, $osmName);
                if ($cityCoords && !$dryRun) {
                    $city->update([
                        'latitude' => $cityCoords['lat'],
                        'longitude' => $cityCoords['lng'],
                    ]);
                }

                // Quartiers
                $quarters = $this->extractQuarters($data);
                foreach ($quarters as $q) {
                    $imported++;
                    if (!$dryRun) {
                        Quarter::updateOrCreate(
                            ['name' => $q['name'], 'city_id' => $city->id],
                            ['latitude' => $q['lat'], 'longitude' => $q['lng']],
                        );
                    } else {
                        $this->line("  [dry] {$city->name} / {$q['name']} ({$q['lat']}, {$q['lng']})");
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("ImportGeoFromOverpass: {$city->name} — {$e->getMessage()}");
            }

            $bar->advance();
            // Respecter le rate-limit Overpass (1 req/s recommandé)
            usleep(1_100_000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Terminé — {$imported} quartiers importés/mis à jour, {$skipped} villes ignorées (pas de données OSM).");

        return self::SUCCESS;
    }

    /**
     * Construit et exécute la requête Overpass QL.
     *
     * @return list<array<string,mixed>>
     */
    private function queryOverpass(string $cityName): array
    {
        $types = self::PLACE_TYPES;

        $query = <<<OVERPASS
[out:json][timeout:30];
area["name"="{$cityName}"]["place"~"city|town"]["boundary"!~"."]->.city;
(
  node["place"~"{$types}"](area.city);
  way["place"~"{$types}"](area.city);
  relation["place"~"{$types}"](area.city);
  node["name"="{$cityName}"]["place"~"city|town"];
);
out center;
OVERPASS;

        $response = Http::timeout(35)
            ->withHeaders(['User-Agent' => 'KeyHome/1.0 (contact@keyhome.app)'])
            ->post(self::OVERPASS_URL, ['data' => $query]);

        if (!$response->ok()) {
            return [];
        }

        return $response->json('elements', []);
    }

    /**
     * @param  list<array<string,mixed>>  $elements
     * @return array{lat:float,lng:float}|null
     */
    private function extractCityCoords(array $elements, string $cityName): ?array
    {
        foreach ($elements as $el) {
            $name = $el['tags']['name'] ?? '';
            if (strtolower($name) === strtolower($cityName)) {
                $lat = (float) ($el['lat'] ?? $el['center']['lat'] ?? 0);
                $lng = (float) ($el['lon'] ?? $el['center']['lon'] ?? 0);
                if ($lat && $lng) {
                    return ['lat' => $lat, 'lng' => $lng];
                }
            }
        }

        return null;
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
