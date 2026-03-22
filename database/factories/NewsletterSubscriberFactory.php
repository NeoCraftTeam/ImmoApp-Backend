<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriber>
 */
final class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'locale' => fake()->randomElement(['fr', 'en']),
            'source' => 'website',
            'confirmed_at' => now(),
        ];
    }

    public function unconfirmed(): static
    {
        return $this->state(fn (): array => [
            'confirmed_at' => null,
        ]);
    }

    public function unsubscribed(): static
    {
        return $this->state(fn (): array => [
            'unsubscribed_at' => now()->subDay(),
        ]);
    }
}
