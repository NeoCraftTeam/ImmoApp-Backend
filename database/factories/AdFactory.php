<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ad> */
class AdFactory extends Factory
{
    protected $model = Ad::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->word(),
            'slug' => $this->faker->slug(),
            'description' => $this->faker->text(),
            'adresse' => $this->faker->address(),
            'price' => $this->faker->randomNumber(5, true), // Random number with 5 digits
            'property_type' => $this->faker->randomElement(['house', 'apartment', 'land', 'studio']),
            'surface_area' => $this->faker->randomNumber(5, false ),
            'bedrooms' => $this->faker->randomDigitNotNull(),
            'bathrooms' => $this->faker->randomDigitNotNull(),
            'has_parking' => $this->faker->boolean(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'status' => $this->faker->randomElement(['available', 'reserved', 'rent']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'user_id' => User::factory(),
            'quarter_id' => Quarter::factory(),
        ];
    }
}
