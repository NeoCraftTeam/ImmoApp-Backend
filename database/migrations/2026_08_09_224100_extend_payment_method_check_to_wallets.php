<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend the `payments_payment_method_check` CHECK constraint to authorise the
 * `apple_pay` and `google_pay` values introduced with Stripe wallet support.
 *
 * Stripe reports wallet payments as `payment_method_details.card.wallet.type`
 * on the charge, so `StripePaymentService::normaliseIntent()` now resolves
 * those to dedicated `PaymentMethod` cases instead of collapsing them into
 * `card`. Both the verify path and the webhook path persist that value via
 * `PaymentMethod::tryFrom()`, so without this widening a *successful* wallet
 * payment would fail its UPDATE with SQLSTATE 23514.
 *
 * `gateway` stays `stripe` — gateway and method remain distinct concerns
 * (see `PaymentMethod::gateway()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_payment_method_check');
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_payment_method_check
            CHECK (payment_method::text IN (
                'orange_money',
                'mobile_money',
                'card',
                'apple_pay',
                'google_pay',
                'stripe',
                'flutterwave'
            ))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_payment_method_check');
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_payment_method_check
            CHECK (payment_method::text IN (
                'orange_money',
                'mobile_money',
                'card',
                'stripe',
                'flutterwave'
            ))
        SQL);
    }
};
