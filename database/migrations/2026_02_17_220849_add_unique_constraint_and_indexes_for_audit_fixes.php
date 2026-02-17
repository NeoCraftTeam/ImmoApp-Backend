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
        // P0: Prevent duplicate unlocked ads (race condition protection)
        Schema::table('unlocked_ads', function (Blueprint $table) {
            $table->unique(['ad_id', 'user_id'], 'unlocked_ads_ad_user_unique');
        });

        // P1: Composite index for interaction debounce queries
        Schema::table('ad_interactions', function (Blueprint $table) {
            $table->index(['user_id', 'ad_id', 'type', 'created_at'], 'idx_interactions_debounce');
            $table->index(['ad_id', 'type', 'created_at'], 'idx_interactions_analytics');
        });

        // P1: Index on ad.status for filtering
        Schema::table('ad', function (Blueprint $table) {
            $table->index('status', 'idx_ad_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unlocked_ads', function (Blueprint $table) {
            $table->dropUnique('unlocked_ads_ad_user_unique');
        });

        Schema::table('ad_interactions', function (Blueprint $table) {
            $table->dropIndex('idx_interactions_debounce');
            $table->dropIndex('idx_interactions_analytics');
        });

        Schema::table('ad', function (Blueprint $table) {
            $table->dropIndex('idx_ad_status');
        });
    }
};
