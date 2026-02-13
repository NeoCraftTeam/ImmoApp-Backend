<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->foreignUuid('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agency_id')->constrained('agency')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name');
            $table->string('billing_period');
            $table->integer('amount');
            $table->string('currency')->default('XOF');
            $table->timestamp('issued_at');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
