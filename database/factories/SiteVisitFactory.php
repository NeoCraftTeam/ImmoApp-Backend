<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SiteVisit> */
final class SiteVisitFactory extends Factory
{
    protected $model = SiteVisit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'session_id' => Str::uuid()->toString(),
            'source' => fake()->randomElement(['direct', 'google', 'facebook', 'referral']),
            'referrer_domain' => fake()->optional(0.5)->domainName(),
            'utm_source' => fake()->optional(0.3)->word(),
            'utm_medium' => fake()->optional(0.3)->randomElement(['cpc', 'organic', 'email', 'social']),
            'utm_campaign' => fake()->optional(0.3)->slug(2),
            'user_id' => fake()->optional(0.5)->passthrough(User::factory()),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'visited_at' => now(),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(['user_id' => null]);
    }

    public function fromGoogle(): static
    {
        return $this->state([
            'source' => 'google',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
        ]);
    }
}
