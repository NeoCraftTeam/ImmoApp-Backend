<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User-facing refund representation.
 *
 * Deliberately omits internal ops columns that must never reach the end user:
 * `admin_note`, `gateway_response` and `processed_by`. Only fields the refund's
 * owner is entitled to see are exposed.
 *
 * @mixin Refund
 */
final class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'is_partial' => (bool) $this->is_partial,
            // Part of the client contract (web `UserRefund`): the gateway's own
            // refund reference and whether loyalty side-effects were reversed.
            // Both concern the owner's own refund — not internal ops data.
            'gateway_refund_id' => $this->gateway_refund_id,
            'side_effects_reversed' => (bool) $this->side_effects_reversed,
            'created_at' => $this->created_at?->toIso8601String(),
            // The refund's own `currency` (top-level) is the source of truth;
            // the `payments` table has no currency column.
            'payment' => $this->whenLoaded('payment', fn (): array => [
                'id' => $this->payment->id,
                'type' => $this->payment->type,
                'amount' => $this->payment->amount,
                'created_at' => $this->payment->created_at?->toIso8601String(),
            ]),
        ];
    }
}
