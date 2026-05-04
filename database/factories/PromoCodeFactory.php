<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PromoCode> */
final class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => PromoCode::CODE_PREFIX.mb_strtoupper(fake()->unique()->bothify('????####')),
            'description' => fake()->sentence(),
            'discount_type' => fake()->randomElement(['percentage', 'fixed']),
            'discount_value' => fake()->randomFloat(2, 5, 50),
            'max_uses' => fake()->numberBetween(10, 1000),
            'used_count' => 0,
            'expires_at' => fake()->dateTimeBetween('+1 week', '+6 months'),
            'is_active' => true,
            'applicable_to' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => [
            'max_uses' => 10,
            'used_count' => 10,
        ]);
    }

    public function percentage(float $value = 10.0): static
    {
        return $this->state([
            'discount_type' => 'percentage',
            'discount_value' => $value,
        ]);
    }

    public function fixed(float $value = 5000.0): static
    {
        return $this->state([
            'discount_type' => 'fixed',
            'discount_value' => $value,
        ]);
    }
}
