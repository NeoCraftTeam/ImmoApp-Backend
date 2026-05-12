<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Defence-in-depth idempotency constraints.
 *
 * - subscriptions.payment_id  — one subscription per payment.
 *   PostgreSQL UNIQUE allows multiple NULLs (subscriptions without a payment
 *   are not affected). No existing data de-duplication needed in dev/preprod.
 *
 * - invoices.subscription_id  — one invoice per subscription.
 *   Column is non-nullable and already a FK, so a plain unique is correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->unique('payment_id', 'subscriptions_payment_id_unique');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique('subscription_id', 'invoices_subscription_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropUnique('subscriptions_payment_id_unique');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_subscription_id_unique');
        });
    }
};
