<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Cashier\Subscription as CashierBase;

/**
 * Custom Cashier subscription model.
 *
 * Pinned to the `cashier_subscriptions` table to avoid colliding with the
 * KeyHome business `subscriptions` table (App\Models\Subscription — agency
 * plans, billed in XAF via Flutterwave). Wired in the container through
 * `Cashier::useSubscriptionModel(CashierSubscription::class)` in
 * `AppServiceProvider::register()`.
 *
 * Cashier exposes the same public API; only the underlying table changes.
 */
class CashierSubscription extends CashierBase
{
    protected $table = 'cashier_subscriptions';
}
