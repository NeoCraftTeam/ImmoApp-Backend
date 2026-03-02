<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscribable_type' => User::class,
            'subscribable_id' => User::factory(),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.fake()->uuid(),
            'public_key' => base64_encode(fake()->sha256()),
            'auth_token' => base64_encode(fake()->md5()),
            'content_encoding' => 'aesgcm',
            'last_used_at' => fake()->optional()->dateTimeBetween('-30 days'),
        ];
    }
}
