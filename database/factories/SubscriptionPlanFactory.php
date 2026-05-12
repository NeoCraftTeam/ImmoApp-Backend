<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(0, 5000, 50000),
            'price_yearly' => fake()->randomFloat(0, 50000, 500000),
            'duration_days' => fake()->randomElement([30, 90, 365]),
            'boost_score' => fake()->numberBetween(10, 50),
            'boost_duration_days' => fake()->numberBetween(7, 30),
            'max_ads' => fake()->optional()->numberBetween(5, 100),
            'features' => ['Boost ads', 'Premium visibility'],
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
