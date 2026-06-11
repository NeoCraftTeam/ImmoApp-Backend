<?php

namespace Database\Factories;

use App\Enums\SubscriptionTier;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tier = fake()->randomElement(SubscriptionTier::cases());

        return [
            'name' => $tier->label(),
            'slug' => $tier->value,
            'tier' => $tier,
            'tier_multiplier' => $tier->multiplier(),
            'description' => $tier->description(),
            'price' => fake()->randomFloat(0, 5000, 50000),
            'price_yearly' => fake()->randomFloat(0, 50000, 500000),
            'duration_days' => 30,
            'boost_score' => fake()->numberBetween(10, 50),
            'boost_duration_days' => $tier->boostDurationDays(),
            'max_ads' => $tier->maxAds(),
            'features' => $tier->features(),
            'is_active' => true,
            'has_trial' => fake()->boolean(30),
            'trial_days' => fake()->randomElement([0, 7, 14, 30]),
            'has_priority_support' => $tier->hasPrioritySupport(),
            'has_analytics' => $tier->hasAnalytics(),
            'has_api_access' => $tier->hasApiAccess(),
            'sort_order' => $tier->sortOrder(),
        ];
    }

    /**
     * Create a Basic tier plan
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Basic',
            'slug' => 'basic',
            'tier' => SubscriptionTier::BASIC,
            'tier_multiplier' => SubscriptionTier::BASIC->multiplier(),
            'description' => SubscriptionTier::BASIC->description(),
            'price' => 15000,
            'price_yearly' => 150000,
            'max_ads' => SubscriptionTier::BASIC->maxAds(),
            'boost_duration_days' => SubscriptionTier::BASIC->boostDurationDays(),
            'features' => SubscriptionTier::BASIC->features(),
            'has_priority_support' => false,
            'has_analytics' => false,
            'has_api_access' => false,
            'sort_order' => 1,
        ]);
    }

    /**
     * Create a Premium tier plan
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Premium',
            'slug' => 'premium',
            'tier' => SubscriptionTier::PREMIUM,
            'tier_multiplier' => SubscriptionTier::PREMIUM->multiplier(),
            'description' => SubscriptionTier::PREMIUM->description(),
            'price' => 35000,
            'price_yearly' => 350000,
            'max_ads' => SubscriptionTier::PREMIUM->maxAds(),
            'boost_duration_days' => SubscriptionTier::PREMIUM->boostDurationDays(),
            'features' => SubscriptionTier::PREMIUM->features(),
            'has_priority_support' => true,
            'has_analytics' => true,
            'has_api_access' => false,
            'sort_order' => 2,
        ]);
    }

    /**
     * Create an Enterprise tier plan
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'tier' => SubscriptionTier::ENTERPRISE,
            'tier_multiplier' => SubscriptionTier::ENTERPRISE->multiplier(),
            'description' => SubscriptionTier::ENTERPRISE->description(),
            'price' => 75000,
            'price_yearly' => 750000,
            'max_ads' => null, // Unlimited
            'boost_duration_days' => SubscriptionTier::ENTERPRISE->boostDurationDays(),
            'features' => SubscriptionTier::ENTERPRISE->features(),
            'has_priority_support' => true,
            'has_analytics' => true,
            'has_api_access' => true,
            'sort_order' => 3,
        ]);
    }

    /**
     * Plan with trial period
     */
    public function withTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => [
            'has_trial' => true,
            'trial_days' => $days,
        ]);
    }
}
