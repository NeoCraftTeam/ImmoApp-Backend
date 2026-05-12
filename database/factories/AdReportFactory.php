<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdReportReason;
use App\Enums\AdReportStatus;
use App\Models\Ad;
use App\Models\AdReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdReport>
 */
class AdReportFactory extends Factory
{
    protected $model = AdReport::class;

    public function definition(): array
    {
        return [
            'ad_id' => Ad::factory(),
            'reporter_id' => User::factory()->customers(),
            'owner_id' => User::factory()->agents(),
            'reason' => $this->faker->randomElement(AdReportReason::cases()),
            'scam_reason' => null,
            'payment_methods' => null,
            'description' => $this->faker->optional()->sentence(),
            'status' => AdReportStatus::PENDING,
            'admin_notes' => null,
            'resolved_at' => null,
            'resolved_by' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
