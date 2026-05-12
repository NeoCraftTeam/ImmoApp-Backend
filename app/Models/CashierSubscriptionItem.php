<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Cashier\SubscriptionItem as CashierItemBase;

/**
 * Custom Cashier subscription item model — pinned to
 * `cashier_subscription_items` (see {@see CashierSubscription} for the
 * naming rationale). Wired via
 * `Cashier::useSubscriptionItemModel(CashierSubscriptionItem::class)`.
 */
class CashierSubscriptionItem extends CashierItemBase
{
    protected $table = 'cashier_subscription_items';
}
