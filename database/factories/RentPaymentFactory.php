<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LeaseContract;
use App\Models\RentPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentPayment> */
final class RentPaymentFactory extends Factory
{
    protected $model = RentPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $received = fake()->dateTimeBetween('-6 months', 'now');
        $periodMonth = (clone $received)->modify('first day of this month');

        return [
            'lease_contract_id' => LeaseContract::factory(),
            'period_month' => $periodMonth,
            'amount' => fake()->numberBetween(30000, 500000),
            'payment_method' => fake()->randomElement(['cash', 'mobile_money', 'bank_transfer', 'other']),
            'received_at' => $received,
            'notes' => fake()->optional(0.3)->sentence(),
            'recorded_by_user_id' => User::factory(),
        ];
    }
}
