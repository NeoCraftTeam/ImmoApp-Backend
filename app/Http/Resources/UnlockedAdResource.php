<?php

namespace App\Http\Resources;

use App\Models\UnlockedAd;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UnlockedAd */
class UnlockedAdResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unlocked_at' => $this->unlocked_at,
            'updated_at' => $this->updated_at,

            'ad_id' => $this->ad_id,
            'user_id' => $this->user_id,
            'payment_id' => $this->payment_id,

            'ad' => new AdResource($this->whenLoaded('ad')),
            'payment' => new paymentResource($this->whenLoaded('payment')),
        ];
    }
}
