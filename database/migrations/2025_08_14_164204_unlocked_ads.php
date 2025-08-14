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
        Schema::create('unlocked_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained('ad')->onDelete('cascade');
            $table->foreignId('user_id') ->constrained()->onDelete('cascade');
            $table->foreignId('payment_id')->constrained('payment')->onDelete('cascade');
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('updated-at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unlocked_ads');
    }
};
