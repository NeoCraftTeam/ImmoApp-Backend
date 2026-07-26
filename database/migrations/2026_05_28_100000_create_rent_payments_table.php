<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rent-collection ledger.
 *
 * One row per actual rent received by the landlord (cash, mobile-money,
 * bank transfer, or other). Distinct from `payments` — that table is for
 * platform fees (credits / subscriptions / unlocks / boosts) flowing
 * through Stripe or Kpay. Rent collection in CEMAC is mostly
 * out-of-band (cash, MM transfer), so this is a manual ledger the
 * landlord maintains, with the option to capture partial payments as
 * multiple rows for the same `period_month`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('lease_contract_id')
                ->constrained('lease_contracts')
                ->cascadeOnDelete();
            // First day of the rental month this collection covers (e.g.
            // 2026-05-01 for May 2026). Multiple rows per (lease, month)
            // are allowed to support partial payments.
            $table->date('period_month');
            $table->unsignedInteger('amount'); // XAF
            $table->string('payment_method'); // cash | mobile_money | bank_transfer | other
            $table->date('received_at');
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->index(['lease_contract_id', 'period_month']);
            $table->index(['recorded_by_user_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_payments');
    }
};
