<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmailPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<EmailPreference> */
final class EmailPreferenceFactory extends Factory
{
    protected $model = EmailPreference::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ad_updates' => true,
            'search_alerts' => true,
            'subscription_updates' => true,
            'survey_notifications' => true,
            'admin_notifications' => true,
            'welcome_emails' => true,
            'engagement_emails' => true,
            'digest_emails' => true,
            'unsubscribe_token' => Str::random(64),
        ];
    }

    public function unsubscribedAll(): static
    {
        return $this->state([
            'ad_updates' => false,
            'search_alerts' => false,
            'subscription_updates' => false,
            'survey_notifications' => false,
            'admin_notifications' => false,
            'welcome_emails' => false,
            'engagement_emails' => false,
            'digest_emails' => false,
        ]);
    }
}
