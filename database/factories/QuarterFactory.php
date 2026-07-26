<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Quarter;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Quarter> */
class QuarterFactory extends Factory
{
    protected $model = Quarter::class;

    public function definition(): array
    {
        $lat = (float) fake()->latitude(-90, 90);
        $lng = (float) fake()->longitude(-180, 180);

        return [
            'name' => fake()->streetName(),
            'latitude' => $lat,
            'longitude' => $lng,
            'location' => Point::makeGeodetic($lat, $lng),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'city_id' => City::factory(),
        ];
    }
}
