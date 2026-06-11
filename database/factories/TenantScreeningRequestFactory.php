<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScreeningDocumentType;
use App\Enums\ScreeningStatus;
use App\Models\LeaseContract;
use App\Models\TenantScreeningRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TenantScreeningRequest> */
final class TenantScreeningRequestFactory extends Factory
{
    protected $model = TenantScreeningRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'lease_contract_id' => LeaseContract::factory(),
            'requested_by' => User::factory(),
            'tenant_name' => fake()->name(),
            'tenant_email' => fake()->safeEmail(),
            'token' => Str::random(64),
            'status' => ScreeningStatus::Pending,
            'required_documents' => [
                ScreeningDocumentType::IdCard->value,
                ScreeningDocumentType::SalarySlip->value,
            ],
            'landlord_notes' => fake()->optional(0.3)->sentence(),
            'expires_at' => now()->addDays(14),
        ];
    }

    public function submitted(): self
    {
        return $this->state(fn (): array => [
            'status' => ScreeningStatus::Submitted,
            'submitted_at' => now()->subDay(),
        ]);
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => ScreeningStatus::Approved,
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now()->subDay(),
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => [
            'status' => ScreeningStatus::Rejected,
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now()->subDay(),
            'review_notes' => fake()->sentence(),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'status' => ScreeningStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }
}
