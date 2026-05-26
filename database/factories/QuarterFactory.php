<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Quarter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Quarter> */
class QuarterFactory extends Factory
{
    protected $model = Quarter::class;

    public function definition(): array
    {
        return [
            'name' => fake()->streetName(),
            'latitude' => fake()->latitude(-90, 90),
            'longitude' => fake()->longitude(-180, 180),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'city_id' => City::factory(),
        ];
    }
}
