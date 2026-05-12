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
        // Renamed to `cashier_subscriptions` to avoid collision with the
        // existing `subscriptions` business table (App\Models\Subscription —
        // agency plans). The Cashier model is overridden via
        // Cashier::useSubscriptionModel(\App\Models\CashierSubscription::class)
        // in AppServiceProvider so the ORM points to the right table.
        Schema::create('cashier_subscriptions', function (Blueprint $table) {
            $table->id();
            // KeyHome `users.id` is UUID (HasUuids), so foreignUuid is required.
            $table->foreignUuid('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'stripe_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_subscriptions');
    }
};
