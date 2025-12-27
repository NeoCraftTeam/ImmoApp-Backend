<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agency_id')->constrained('agency')->onDelete('cascade');
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->onDelete('restrict');
            $table->string('status')->default('pending'); // pending, active, expired, cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->onDelete('set null');
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->index('ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
