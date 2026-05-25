<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisputeMessage>
 */
class DisputeMessageFactory extends Factory
{
    protected $model = DisputeMessage::class;

    public function definition(): array
    {
        return [
            'dispute_id' => Dispute::factory(),
            'sender_id' => User::factory(),
            'body' => $this->faker->paragraph(),
            'is_internal' => false,
        ];
    }

    public function internal(): self
    {
        return $this->state(fn (): array => ['is_internal' => true]);
    }
}
