<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SearchAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SearchAlert> */
class SearchAlertFactory extends Factory
{
    protected $model = SearchAlert::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->sentence(3),
            'city_name' => fake()->city(),
            'query' => fake()->word(),
            'is_active' => true,
        ];
    }
}
