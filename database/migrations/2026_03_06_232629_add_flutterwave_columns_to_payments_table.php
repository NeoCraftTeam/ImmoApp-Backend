<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make transaction_id nullable so Flutterwave payments can be created
        // before the external charge ID is available.
        DB::statement('ALTER TABLE payments ALTER COLUMN transaction_id DROP NOT NULL');

        Schema::table('payments', function (Blueprint $table): void {
            // Which gateway processed this payment (flutterwave)
            $table->string('gateway')->default('flutterwave')->after('period');

            // The checkout URL or payment instruction returned by the gateway
            $table->string('payment_link', 1000)->nullable()->after('gateway');

            // Raw gateway response stored for audit / debugging
            $table->json('gateway_response')->nullable()->after('payment_link');

            // Phone number used for mobile money
            $table->string('phone_number', 30)->nullable()->after('gateway_response');

            // Index for quick gateway + transaction lookups
            $table->index(['gateway', 'transaction_id']);
        });

        // Drop the old CHECK constraint first so the normalisation UPDATE below is not
        // blocked by values ('flutterwave', legacy strings) that the old constraint
        // doesn't allow. The new constraint is re-added after the UPDATE.
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_payment_method_check');

        // Legacy rows may store Flutterwave `payment_type` strings (visa, banktransfer, …) or
        // mixed casing ; normalise everything to PaymentMethod-backed values before CHECK.
        DB::statement(<<<'SQL'
            UPDATE payments
            SET payment_method = CASE
                WHEN btrim(payment_method::text) = '' THEN 'flutterwave'
                WHEN lower(btrim(payment_method::text)) IN (
                    'orange_money','mobile_money','card','stripe','flutterwave'
                ) THEN lower(btrim(payment_method::text))
                WHEN lower(btrim(payment_method::text)) LIKE '%orange%' THEN 'orange_money'
                WHEN lower(btrim(payment_method::text)) LIKE '%mtn%'
                    OR lower(btrim(payment_method::text)) LIKE '%mobile%' THEN 'mobile_money'
                WHEN lower(btrim(payment_method::text)) LIKE '%card%'
                    OR lower(btrim(payment_method::text)) IN ('visa','mastercard','verve','amex','discover','diners') THEN 'card'
                ELSE 'flutterwave'
            END
            WHERE payment_method IS NOT NULL
                AND lower(btrim(payment_method::text)) NOT IN (
                    'orange_money','mobile_money','card','stripe','flutterwave'
                )
        SQL);
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_payment_method_check CHECK (payment_method::text IN ('orange_money','mobile_money','card','stripe','flutterwave'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_payment_method_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_payment_method_check CHECK (payment_method::text IN ('orange_money','mobile_money','stripe'));");

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['gateway', 'transaction_id']);
            $table->dropColumn(['gateway', 'payment_link', 'gateway_response', 'phone_number']);
        });

        DB::statement('ALTER TABLE payments ALTER COLUMN transaction_id SET NOT NULL');
    }
};
