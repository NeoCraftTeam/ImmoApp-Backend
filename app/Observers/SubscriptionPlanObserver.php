<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidate cached subscription/boost plan lists whenever a SubscriptionPlan
 * is created, updated, or deleted from Filament admin.
 */
final class SubscriptionPlanObserver
{
    public function saved(SubscriptionPlan $plan): void
    {
        $this->forget();
    }

    public function deleted(SubscriptionPlan $plan): void
    {
        $this->forget();
    }

    private function forget(): void
    {
        Cache::forget('subscription:plans:active');
        Cache::forget('boost:plans:active');
    }
}
