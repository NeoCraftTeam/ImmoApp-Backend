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
        $title = $this->faker->sentence();
        $cityData = $this->getCitiesData();

        $latitude = $cityData['latitude'];
        $longitude = $cityData['longitude'];
        $cityName = $cityData['name'];

        // Utilise le nom de la ville dans l'adresse
        $address = $this->faker->streetAddress().', '.$cityName;

        return [
            'title' => $title,
            'slug' => Ad::generateUniqueSlug($title),
            'description' => $this->faker->paragraph(),
            'adresse' => $address,
            'price' => $this->faker->numberBetween(25000, 150000), // Random number with 5 digits
            'surface_area' => $this->faker->randomNumber(5, false),
            'bedrooms' => $this->faker->randomDigitNotNull(),
            'bathrooms' => $this->faker->randomDigitNotNull(),
            'has_parking' => $this->faker->boolean(),
            'location' => "POINT($longitude $latitude)",
            'status' => $this->faker->randomElement(['available', 'reserved', 'rent']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'user_id' => User::factory(),
            'quarter_id' => Quarter::factory(),
            // Each ad is linked to a type
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
