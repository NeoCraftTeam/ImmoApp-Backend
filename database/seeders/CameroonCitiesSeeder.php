<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\Quarter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CameroonCitiesSeeder extends Seeder
{
    /**
     * Localities parsed from cities.sql.
     *
     * @var array<int, array{geonameid: int, name: string, latitude: float, longitude: float, feature_code: string, admin1_code: string, population: int}>
     */
    private array $entries = [];

    public function run(): void
    {
        $this->command->info('📍 Importing Cameroon cities and quarters from cities.sql...');

        $this->parseSqlFile();

        $this->command->info('  Parsed '.count($this->entries).' localities');

        $cityCandidates = [];
        $quarterCandidates = [];

        foreach ($this->entries as $entry) {
            if ($this->isCity($entry)) {
                $cityCandidates[] = $entry;
            } else {
                $quarterCandidates[] = $entry;
            }
        }

        $uniqueCities = $this->deduplicateCities($cityCandidates, $quarterCandidates);

        $this->command->info('  Cities: '.count($uniqueCities).', Quarters: '.count($quarterCandidates));

        $cityModels = $this->createCities($uniqueCities);
        $this->createQuarters($quarterCandidates, $cityModels);

        $this->command->info('  ✅ Done — '.count($cityModels).' cities, '.count($quarterCandidates).' quarters');
    }

    private function parseSqlFile(): void
    {
        $content = file_get_contents(database_path('data/cities.sql'));

        preg_match_all(
            "/\((\d+),\s*'([^']*)',\s*([\d.+-]+),\s*([\d.+-]+),\s*'([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)',\s*'([^']*)',\s*(\d+),\s*'([^']*)'\)/u",
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $this->entries[] = [
                'geonameid' => (int) $match[1],
                'name' => str_replace("''", "'", $match[2]),
                'latitude' => (float) $match[3],
                'longitude' => (float) $match[4],
                'feature_code' => $match[6],
                'admin1_code' => $match[8],
                'population' => (int) $match[10],
            ];
        }
    }

    /**
     * A locality is considered a "city" if it is a capital, regional capital, or has significant population.
     */
    private function isCity(array $entry): bool
    {
        if (in_array($entry['feature_code'], ['PPLC', 'PPLA', 'PPLA2'])) {
            return true;
        }

        return $entry['population'] >= 10000;
    }

    /**
     * Deduplicate cities by name, keeping the entry with highest population.
     * Demoted duplicates are moved to the quarter candidates list.
     *
     * @return array<string, array>
     */
    private function deduplicateCities(array $cityCandidates, array &$quarterCandidates): array
    {
        $uniqueCities = [];

        foreach ($cityCandidates as $entry) {
            $key = mb_strtolower($entry['name']);

            if (!isset($uniqueCities[$key]) || $entry['population'] > $uniqueCities[$key]['population']) {
                if (isset($uniqueCities[$key])) {
                    $quarterCandidates[] = $uniqueCities[$key];
                }
                $uniqueCities[$key] = $entry;
            } else {
                $quarterCandidates[] = $entry;
            }
        }

        $this->ensureAllRegionsHaveCity($uniqueCities, $quarterCandidates);

        return $uniqueCities;
    }

    /**
     * If a region has no city, promote its most populated entry.
     */
    private function ensureAllRegionsHaveCity(array &$uniqueCities, array &$quarterCandidates): void
    {
        $regionsCovered = [];
        foreach ($uniqueCities as $city) {
            $regionsCovered[$city['admin1_code']] = true;
        }

        $regionBest = [];
        foreach ($quarterCandidates as $idx => $entry) {
            $region = $entry['admin1_code'];
            if (isset($regionsCovered[$region])) {
                continue;
            }

            if (!isset($regionBest[$region]) || $entry['population'] > $regionBest[$region]['population']) {
                $regionBest[$region] = ['index' => $idx, ...$entry];
            }
        }

        foreach ($regionBest as $region => $best) {
            $key = mb_strtolower($best['name']);
            $uniqueCities[$key] = $best;
            unset($quarterCandidates[$best['index']]);
            $quarterCandidates = array_values($quarterCandidates);
        }
    }

    /**
     * @return array<string, array{model: City, lat: float, lng: float, admin1: string}>
     */
    private function createCities(array $uniqueCities): array
    {
        $cityModels = [];

        foreach ($uniqueCities as $entry) {
            $city = City::create(['name' => $entry['name']]);

            $cityModels[] = [
                'model' => $city,
                'lat' => $entry['latitude'],
                'lng' => $entry['longitude'],
                'admin1' => $entry['admin1_code'],
                'population' => $entry['population'],
            ];
        }

        return $cityModels;
    }

    private function createQuarters(array $quarterCandidates, array $cityModels): void
    {
        $regionIndex = [];
        foreach ($cityModels as $cityData) {
            $regionIndex[$cityData['admin1']][] = $cityData;
        }

        $coordinatesMap = [];
        $insertData = [];

        foreach ($cityModels as $cityData) {
            $id = Str::orderedUuid()->toString();
            $insertData[] = [
                'id' => $id,
                'name' => 'Centre-ville',
                'city_id' => $cityData['model']->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $coordinatesMap[$id] = ['lat' => $cityData['lat'], 'lng' => $cityData['lng']];
        }

        foreach ($quarterCandidates as $entry) {
            $cityId = $this->findNearestCity($entry, $regionIndex, $cityModels);
            $id = Str::orderedUuid()->toString();

            $insertData[] = [
                'id' => $id,
                'name' => $entry['name'],
                'city_id' => $cityId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $coordinatesMap[$id] = ['lat' => $entry['latitude'], 'lng' => $entry['longitude']];
        }

        file_put_contents(
            storage_path('app/quarter_coordinates.json'),
            json_encode($coordinatesMap)
        );

        foreach (array_chunk($insertData, 2000) as $chunk) {
            Quarter::insert($chunk);
        }
    }

    private function findNearestCity(array $entry, array $regionIndex, array $allCities): string
    {
        $candidates = $regionIndex[$entry['admin1_code']] ?? $allCities;

        $nearestId = $candidates[0]['model']->id;
        $nearestDist = PHP_FLOAT_MAX;

        foreach ($candidates as $cityData) {
            $dist = pow($entry['latitude'] - $cityData['lat'], 2) + pow($entry['longitude'] - $cityData['lng'], 2);

            if ($dist < $nearestDist) {
                $nearestDist = $dist;
                $nearestId = $cityData['model']->id;
            }
        }

        return $nearestId;
    }
}
