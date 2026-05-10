<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend the `payments_payment_method_check` CHECK constraint to authorise
 * the `card` value introduced with the Stripe integration.
 *
 * The previous list (orange_money / mobile_money / stripe / flutterwave) was
 * tailored to Flutterwave's hosted-checkout method types ; with Stripe in
 * the mix, customers paying by card are saved with `payment_method = 'card'`
 * and `gateway = 'stripe'` (gateway and method are now distinct concerns —
 * see `PaymentMethod::gateway()`).
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
                'stripe',
                'flutterwave'
            ))
        SQL);
    }
};
