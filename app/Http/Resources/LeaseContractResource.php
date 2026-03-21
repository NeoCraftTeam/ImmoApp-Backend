<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LeaseContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaseContract */
final class LeaseContractResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'unit_reference' => $this->unit_reference,
            'tenant_name' => $this->tenant_name,
            'tenant_phone' => $this->tenant_phone,
            'tenant_email' => $this->tenant_email,
            'tenant_id_number' => $this->tenant_id_number,
            'lease_start' => $this->lease_start?->format('Y-m-d'),
            'lease_end' => $this->lease_end?->format('Y-m-d'),
            'lease_duration_months' => $this->lease_duration_months,
            'monthly_rent' => (float) $this->monthly_rent,
            'deposit_amount' => $this->deposit_amount ? (float) $this->deposit_amount : null,
            'special_conditions' => $this->special_conditions,
            'created_at' => $this->created_at?->toIso8601String(),

            'ad' => new AdResource($this->whenLoaded('ad')),
        ];
    }
}
