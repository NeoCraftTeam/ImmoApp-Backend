<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DisputeEvidenceType;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisputeEvidence>
 */
class DisputeEvidenceFactory extends Factory
{
    protected $model = DisputeEvidence::class;

    public function definition(): array
    {
        return [
            'dispute_id' => Dispute::factory(),
            'uploader_id' => User::factory(),
            'type' => $this->faker->randomElement(DisputeEvidenceType::cases()),
            'disk' => 'public',
            'path' => 'disputes/'.$this->faker->uuid().'/'.$this->faker->word().'.jpg',
            'original_name' => $this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(1024, 5_000_000),
        ];
    }
}
