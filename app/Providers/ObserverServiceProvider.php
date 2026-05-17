<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Ad;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\SubscriptionPlan;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Observers\ActivityObserver;
use App\Observers\AdObserver;
use App\Observers\PaymentObserver;
use App\Observers\PointPackageObserver;
use App\Observers\SubscriptionPlanObserver;
use App\Observers\TentativeReservationObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

/**
 * Registers all Eloquent model observers.
 *
 * Extracted from AppServiceProvider to keep it focused on service bindings.
 * Each observer is responsible for side-effects triggered by model lifecycle events.
 */
class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap model observers.
     */
    public function boot(): void
    {
        Payment::observe(PaymentObserver::class);
        Ad::observe(AdObserver::class);
        TentativeReservation::observe(TentativeReservationObserver::class);
        Activity::observe(ActivityObserver::class);
        User::observe(UserObserver::class);
        PointPackage::observe(PointPackageObserver::class);
        SubscriptionPlan::observe(SubscriptionPlanObserver::class);
    }
}
