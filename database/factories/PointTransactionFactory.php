<?php

namespace Database\Factories;

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PointTransaction> */
class PointTransactionFactory extends Factory
{
    protected $model = PointTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(PointTransactionType::cases())->value,
            'points' => fake()->numberBetween(-5, 50),
            'description' => fake()->sentence(),
            'payment_id' => null,
            'ad_id' => null,
        ];
    }

    public function credit(int $points = 10): static
    {
        return $this->state([
            'type' => PointTransactionType::PURCHASE->value,
            'points' => $points,
        ]);
    }

    public function debit(int $points = 2): static
    {
        return $this->state([
            'type' => PointTransactionType::UNLOCK->value,
            'points' => -$points,
        ]);
    }
}
