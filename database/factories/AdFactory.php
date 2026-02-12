<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\AdType;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ad> */
class AdFactory extends Factory
{
    protected static $citiesData = null;

    protected $model = Ad::class;

    public function definition(): array
    {
        $cityData = $this->getCitiesData();
        $latitude = $cityData['latitude'];
        $longitude = $cityData['longitude'];
        $cityName = $cityData['name'];

        $quarter = Quarter::whereHas('city')
            ->inRandomOrder()
            ->first();

        $prefixes = [
            'Appartement moderne', 'Belle villa', 'Studio meublé',
            'Maison spacieuse', 'Duplex de standing', 'Chambre moderne',
            'Local commercial', 'Terrain viabilisé', 'Appartement 3 pièces',
            'Résidence sécurisée', 'Penthouse lumineux', 'Loft contemporain',
        ];
        $title = fake()->randomElement($prefixes).' à '.($quarter?->name ?? $cityName).' - '.$cityName;
        $address = fake()->streetAddress().', '.($quarter?->name ?? $cityName).', '.$cityName;

        return [
            'title' => $title,
            'slug' => Ad::generateUniqueSlug($title),
            'description' => fake()->realText(300),
            'adresse' => $address,
            'price' => fake()->randomElement([15000, 25000, 35000, 50000, 75000, 100000, 150000, 200000, 300000, 500000]),
            'surface_area' => fake()->randomElement([20, 35, 50, 75, 100, 150, 200, 300, 500]),
            'bedrooms' => fake()->numberBetween(1, 6),
            'bathrooms' => fake()->numberBetween(1, 4),
            'has_parking' => fake()->boolean(),
            'location' => "POINT($longitude $latitude)",
            'status' => fake()->randomElement(['available', 'available', 'available', 'reserved', 'rent']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'user_id' => User::factory(),
            'quarter_id' => $quarter?->id ?? Quarter::factory(),
            'type_id' => AdType::inRandomOrder()->first()->id ?? AdType::factory(),
        ];
    }

    protected function getCitiesData(): array
    {
        if (self::$citiesData === null) {
            $path = database_path('data/cities.sql');
            $content = file_get_contents($path);

            // Pattern regex pour extraire : name (position 2), latitude (position 3), longitude (position 4)
            // Format: (geonameid, 'name', latitude, longitude, ...)
            preg_match_all(
                "/\(\d+,\s*'([^']+)',\s*([0-9.-]+),\s*([0-9.-]+),/",
                $content,
                $matches,
                PREG_SET_ORDER
            );

            self::$citiesData = [];
            foreach ($matches as $match) {
                self::$citiesData[] = [
                    'name' => $match[1],
                    'latitude' => (float) $match[2],
                    'longitude' => (float) $match[3],
                ];
            }
        }

        // Retourne une ville aléatoire
        return self::$citiesData[array_rand(self::$citiesData)];
    }
}
