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
        Schema::table('unlocked_ads', function (Blueprint $table): void {
            $table->dropForeign(['payment_id']);
            $table->foreignUuid('payment_id')->nullable()->change();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unlocked_ads', function (Blueprint $table): void {
            $table->dropForeign(['payment_id']);
            $table->foreignUuid('payment_id')->nullable(false)->change();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
        });
    }
};
