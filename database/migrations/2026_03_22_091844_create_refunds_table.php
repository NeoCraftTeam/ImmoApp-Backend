<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('XAF');
            $table->string('status', 20)->default('pending');
            $table->string('reason');
            $table->string('gateway_refund_id')->nullable();
            $table->json('gateway_response')->nullable();
            $table->boolean('is_partial')->default(false);
            $table->boolean('side_effects_reversed')->default(false);
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
