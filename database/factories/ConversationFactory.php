<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conversation> */
final class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ad_id' => Ad::factory(),
            'tenant_id' => User::factory(),
            'landlord_id' => User::factory(),
            'status' => ConversationStatus::Active,
            'tenant_last_read_at' => null,
            'landlord_last_read_at' => null,
            'last_message_at' => null,
            'last_message_preview' => null,
            'last_message_id' => null,
        ];
    }
}
