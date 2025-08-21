<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Quarter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quarter> */
class QuarterFactory extends Factory
{
    protected $model = Quarter::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->streetName(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'city_id' => City::factory(),
        ];
    }
}
