<?php

declare(strict_types=1);

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
        Schema::table('ad', function (Blueprint $table) {
            // Subscription sponsorship tracking
            $table->boolean('is_subscription_sponsored')->default(false)->after('is_boosted');
            $table->string('subscription_tier', 30)->nullable()->after('is_subscription_sponsored');

            // Impression tracking for rotation strategy
            $table->timestamp('last_shown_at')->nullable()->after('boosted_at');
            $table->unsignedInteger('impression_count')->default(0)->after('last_shown_at');

            // Composite index for feed ranking queries
            // Order: subscription_sponsored DESC, boost_score DESC, created_at DESC
            $table->index(
                ['is_subscription_sponsored', 'boost_score', 'created_at', 'status', 'is_visible'],
                'idx_ad_feed_ranking'
            );

            // Index for rotation queries (recently shown ads)
            $table->index('last_shown_at', 'idx_ad_last_shown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropIndex('idx_ad_feed_ranking');
            $table->dropIndex('idx_ad_last_shown');
            $table->dropColumn([
                'is_subscription_sponsored',
                'subscription_tier',
                'last_shown_at',
                'impression_count',
            ]);
        });
    }
};
