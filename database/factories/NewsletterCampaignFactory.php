<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NewsletterCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterCampaign>
 */
final class NewsletterCampaignFactory extends Factory
{
    protected $model = NewsletterCampaign::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(),
            'body' => '<p>'.fake()->paragraphs(3, true).'</p>',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'sent_at' => now(),
            'recipients_count' => fake()->numberBetween(10, 500),
        ]);
    }
}
