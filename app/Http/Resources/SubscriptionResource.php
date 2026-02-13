<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => new SubscriptionPlanResource($this->whenLoaded('plan')),
            'billing_period' => $this->billing_period,
            'status' => $this->status->value,
            'amount_paid' => (int) $this->amount_paid,
            'amount_paid_formatted' => number_format((float) $this->amount_paid, 0, ',', ' ').' FCFA',
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'days_remaining' => $this->daysRemaining(),
            'is_active' => $this->isActive(),
            'auto_renew' => $this->auto_renew,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
