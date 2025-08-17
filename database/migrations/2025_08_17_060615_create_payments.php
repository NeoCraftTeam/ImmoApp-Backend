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
        Schema::create('payment', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['unlock', 'boost', 'subscription'])->default('unlock');
            $table->decimal('amount', 10, 2);
            $table->string('transaction_id', 100)->unique()->nullable(false);
            $table->enum('payment_method', ['orange_money', 'mobile_money', 'stripe'])->nullable(false);
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->references('id')->on('user');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending')->nullable(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment');
    }
};
