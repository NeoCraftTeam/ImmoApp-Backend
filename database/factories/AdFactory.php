<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\AdType;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ad> */
class AdFactory extends Factory
{
    protected $model = Ad::class;

    public function definition(): array
    {
        $title = $this->faker->sentence();
        $latitude = $this->faker->latitude();
        $longitude = $this->faker->longitude();

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => $this->faker->paragraph(),
            'adresse' => $this->faker->address(),
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
}
