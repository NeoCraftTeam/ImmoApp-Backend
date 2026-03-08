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
            // Which gateway processed this payment (flutterwave | fedapay)
            $table->string('gateway')->default('fedapay')->after('period');

            // The checkout URL or payment instruction returned by the gateway
            $table->string('payment_link', 1000)->nullable()->after('gateway');

            // Raw gateway response stored for audit / debugging
            $table->json('gateway_response')->nullable()->after('payment_link');

            // Phone number used for mobile money
            $table->string('phone_number', 30)->nullable()->after('gateway_response');

            // Index for quick gateway + transaction lookups
            $table->index(['gateway', 'transaction_id']);
        });

        // Add 'flutterwave' to the payment_method check constraint
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_payment_method_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_payment_method_check CHECK (payment_method::text IN ('orange_money','mobile_money','stripe','fedapay','flutterwave'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_payment_method_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_payment_method_check CHECK (payment_method::text IN ('orange_money','mobile_money','stripe','fedapay'))");

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['gateway', 'transaction_id']);
            $table->dropColumn(['gateway', 'payment_link', 'gateway_response', 'phone_number']);
        });

        DB::statement('ALTER TABLE payments ALTER COLUMN transaction_id SET NOT NULL');
    }
};
