<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit-driven index cleanup (2026-05-06):
 *
 * 1. ADD composite index (user_id, status, created_at) for owner "my ads" listing.
 * 2. DROP redundant standalone status indexes — the composite index
 *    `ad_feed_composite_index (status, is_visible, created_at)` already covers
 *    leftmost-column `status` queries, making standalone `idx_ad_status` and
 *    `ad_status_index` unnecessary overhead on writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            // L-2: Owner ads listing — covers WHERE user_id = ? AND status = ? ORDER BY created_at DESC
            $table->index(['user_id', 'status', 'created_at'], 'ad_owner_listing_idx');
        });

        // L-4: Drop redundant standalone status indexes (idempotent via IF EXISTS)
        DB::statement('DROP INDEX IF EXISTS idx_ad_status');
        DB::statement('DROP INDEX IF EXISTS ad_status_index');
    }

    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropIndex('ad_owner_listing_idx');
        });

        Schema::table('ad', function (Blueprint $table) {
            $table->index('status', 'idx_ad_status');
            $table->index('status', 'ad_status_index');
        });
    }
};
