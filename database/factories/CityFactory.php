<?php

namespace Database\Factories;

use App\Models\City;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<City> */
class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        $lat = (float) fake()->latitude(-90, 90);
        $lng = (float) fake()->longitude(-180, 180);

        return [
            'name' => fake()->city(),
            'country' => fake()->country(),
            'latitude' => $lat,
            'longitude' => $lng,
            'location' => Point::makeGeodetic($lat, $lng),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
