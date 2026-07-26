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
    #[\Override]
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
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'is_on_trial' => $this->isOnTrial(),
            'trial_days_remaining' => $this->trialDaysRemaining(),
            'days_remaining' => $this->daysRemaining(),
            'is_active' => $this->isActive(),
            'auto_renew' => $this->auto_renew,
            'renewal_count' => $this->renewal_count,
            'renewed_at' => $this->renewed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
