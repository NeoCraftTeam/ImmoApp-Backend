<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RentPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RentPayment */
final class RentPaymentResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lease_contract_id' => $this->lease_contract_id,
            'period_month' => $this->period_month?->format('Y-m-d'),
            'amount' => (int) $this->amount,
            'payment_method' => $this->payment_method,
            'received_at' => $this->received_at?->format('Y-m-d'),
            'notes' => $this->notes,
            'recorded_by_user_id' => $this->recorded_by_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
