<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'billing_period' => fake()->randomElement(['monthly', 'yearly']),
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'amount_paid' => fake()->randomFloat(0, 5000, 50000),
            'auto_renew' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);
    }

    public function onTrial(int $days = 14): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays($days),
            'ends_at' => now()->addDays($days + 30),
        ]);
    }

    public function trialEnded(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subDays(20),
            'trial_ends_at' => now()->subDays(5),
            'ends_at' => now()->addDays(10),
        ]);
    }

    public function autoRenew(): static
    {
        return $this->state(fn () => [
            'auto_renew' => true,
        ]);
    }

    public function renewed(int $count = 1): static
    {
        return $this->state(fn () => [
            'renewal_count' => $count,
            'renewed_at' => now()->subDays(30),
        ]);
    }
}
