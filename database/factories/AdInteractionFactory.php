<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdInteraction> */
final class AdInteractionFactory extends Factory
{
    protected $model = AdInteraction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ad_id' => Ad::factory(),
            'type' => fake()->randomElement(['view', 'favorite', 'unfavorite', 'search', 'unlock', 'impression', 'share', 'contact_click', 'phone_click']),
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    public function view(): static
    {
        return $this->state(['type' => 'view']);
    }

    public function favorite(): static
    {
        return $this->state(['type' => 'favorite']);
    }

    public function contactClick(): static
    {
        return $this->state(['type' => 'contact_click']);
    }

    public function phoneClick(): static
    {
        return $this->state(['type' => 'phone_click']);
    }
}
