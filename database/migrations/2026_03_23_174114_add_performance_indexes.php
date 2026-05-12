<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ad table — commonly filtered columns
        Schema::table('ad', function (Blueprint $table) {
            $table->index('price', 'ad_price_index');
            $table->index('agency_id', 'ad_agency_id_index');
            $table->index(['status', 'is_visible', 'created_at'], 'ad_feed_composite_index');
        });

        // Reviews — lookup by ad and user
        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['ad_id', 'user_id'], 'reviews_ad_user_index');
            $table->index('is_verified', 'reviews_is_verified_index');
        });

        // Payments — lookup by user and status
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'payments_user_status_index');
        });

        // Users — login lookups
        Schema::table('users', function (Blueprint $table) {
            $table->index('agency_id', 'users_agency_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropIndex('ad_price_index');
            $table->dropIndex('ad_agency_id_index');
            $table->dropIndex('ad_feed_composite_index');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_ad_user_index');
            $table->dropIndex('reviews_is_verified_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_user_status_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_agency_id_index');
        });
    }
};
