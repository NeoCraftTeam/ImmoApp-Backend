<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PropertyAttributeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropertyAttribute>
 */
class PropertyAttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_attribute_category_id' => PropertyAttributeCategory::factory(),
            'name' => fake()->unique()->word(),
            'slug' => fn (array $attributes) => \Illuminate\Support\Str::slug($attributes['name']),
            'icon' => 'CheckCircleOutline',
            'admin_icon' => 'heroicon-o-check-circle',
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
