<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<City> */
class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'country' => fake()->country(),
            'latitude' => fake()->latitude(-90, 90),
            'longitude' => fake()->longitude(-180, 180),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
