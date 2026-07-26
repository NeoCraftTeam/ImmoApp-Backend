<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeaseStatus;
use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaseContract> */
final class LeaseContractFactory extends Factory
{
    protected $model = LeaseContract::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', 'now');
        $months = fake()->randomElement([6, 12, 24, 36]);

        return [
            'user_id' => User::factory(),
            'ad_id' => Ad::factory(),
            'unit_reference' => strtoupper(fake()->bothify('??-###')),
            'contract_number' => 'LC-'.fake()->unique()->numerify('######'),
            'tenant_name' => fake()->name(),
            'tenant_phone' => fake()->phoneNumber(),
            'tenant_email' => fake()->safeEmail(),
            'tenant_id_number' => fake()->numerify('############'),
            'lease_start' => $start,
            'lease_end' => (clone $start)->modify("+{$months} months"),
            'lease_duration_months' => $months,
            'monthly_rent' => fake()->numberBetween(30000, 500000),
            'deposit_amount' => fake()->numberBetween(50000, 1000000),
            'special_conditions' => fake()->optional(0.3)->sentence(),
            'pdf_path' => 'lease-contracts/test-'.fake()->uuid().'.pdf',
            'status' => LeaseStatus::Active,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'status' => LeaseStatus::Expired,
            'lease_start' => fake()->dateTimeBetween('-3 years', '-2 years'),
            'lease_end' => fake()->dateTimeBetween('-1 year', '-1 month'),
        ]);
    }

    public function terminated(): self
    {
        return $this->state(fn (): array => [
            'status' => LeaseStatus::Terminated,
            'terminated_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'termination_reason' => fake()->sentence(),
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (): array => [
            'status' => LeaseStatus::Archived,
            'archived_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ]);
    }
}
