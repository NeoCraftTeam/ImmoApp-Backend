<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DisputeStatus;
use App\Enums\DisputeType;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
{
    protected $model = Dispute::class;

    public function definition(): array
    {
        return [
            'reference' => 'KH-LITIGE-'.now()->format('Y').'-'.strtoupper(Str::random(6)),
            'type' => $this->faker->randomElement(DisputeType::cases()),
            'status' => DisputeStatus::OPEN,
            'initiator_id' => User::factory(),
            'respondent_id' => User::factory(),
            'admin_id' => null,
            'ad_id' => null,
            'lease_id' => null,
            'payment_id' => null,
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(3),
            'amount_claimed' => $this->faker->optional()->numberBetween(10_000, 5_000_000),
            'resolution_note' => null,
            'sla_deadline' => now()->addDays(7),
            'resolved_at' => null,
        ];
    }

    public function underReview(): self
    {
        return $this->state(fn (): array => ['status' => DisputeStatus::UNDER_REVIEW]);
    }

    public function mediation(): self
    {
        return $this->state(fn (): array => ['status' => DisputeStatus::MEDIATION]);
    }

    public function resolved(DisputeStatus $resolution = DisputeStatus::RESOLVED_AMICABLY): self
    {
        return $this->state(fn (): array => [
            'status' => $resolution,
            'resolved_at' => now(),
            'resolution_note' => 'Accord trouvé entre les parties.',
        ]);
    }
}
