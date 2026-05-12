<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(500, 50000),
            'currency' => 'XAF',
            'status' => RefundStatus::Pending,
            'reason' => fake()->sentence(),
            'is_partial' => false,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => RefundStatus::Completed,
            'gateway_refund_id' => 'FLW-REF-'.fake()->unique()->numerify('######'),
            'side_effects_reversed' => true,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => RefundStatus::Failed,
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (): array => [
            'is_partial' => true,
        ]);
    }
}
