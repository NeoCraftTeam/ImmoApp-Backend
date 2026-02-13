<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlan
 */
class SubscriptionPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_monthly' => (int) $this->price,
            'price_yearly' => $this->price_yearly ? (int) $this->price_yearly : null,
            'price_monthly_formatted' => number_format((float) $this->price, 0, ',', ' ').' FCFA',
            'price_yearly_formatted' => $this->price_yearly
                ? number_format((float) $this->price_yearly, 0, ',', ' ').' FCFA'
                : null,
            'yearly_savings' => $this->price_yearly
                ? (int) (($this->price * 12) - $this->price_yearly)
                : null,
            'duration_days' => $this->duration_days,
            'boost_score' => $this->boost_score,
            'boost_duration_days' => $this->boost_duration_days,
            'max_ads' => $this->max_ads,
            'is_unlimited' => $this->hasUnlimitedAds(),
            'features' => $this->features ?? [],
            'sort_order' => $this->sort_order,
        ];
    }
}
