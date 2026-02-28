<?php

namespace Database\Factories;

use App\Models\PointPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PointPackage> */
class PointPackageFactory extends Factory
{
    protected $model = PointPackage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true), // e.g. "Pack Pro"
            'price' => fake()->randomElement([1000, 2000, 4000, 7000, 10000]),
            'points_awarded' => fake()->randomElement([5, 10, 25, 50, 120]),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
