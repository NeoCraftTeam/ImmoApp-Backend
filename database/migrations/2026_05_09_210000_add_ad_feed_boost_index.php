<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Public-feed performance index.
 *
 * The home-feed query (`/api/v1/ads`, `/api/v1/ads/feed`) sorts by
 * `boost_score DESC, created_at DESC, id DESC` over the subset of rows that
 * are visible AND publicly listed (status IN available, reserved). The
 * existing `ad_feed_composite_index (status, is_visible, created_at)` only
 * helps the WHERE clause — Postgres still has to perform an external sort
 * for the ORDER BY, which becomes expensive past ~50k rows.
 *
 * This **partial covering index** stores the rows in feed-display order so
 * the planner can stream tuples directly without a Sort node. `id` is the
 * unique tiebreaker required for stable cursor pagination (cursorPaginate).
 *
 * Postgres-only (CREATE INDEX … WHERE … is not portable). Idempotent —
 * uses CONCURRENTLY only outside transactions and drops the index defensively.
 */
return new class extends Migration
{
    /**
     * CONCURRENTLY indexes cannot run inside a transaction. Disable the
     * implicit migration transaction so the index build does not block writes.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Drop any older variant defensively before re-creating.
        DB::statement('DROP INDEX IF EXISTS ad_feed_boost_idx');

        DB::statement(<<<'SQL'
CREATE INDEX CONCURRENTLY IF NOT EXISTS ad_feed_boost_idx
ON ad (boost_score DESC, created_at DESC, id DESC)
WHERE is_visible = true AND status IN ('available', 'reserved')
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ad_feed_boost_idx');
    }
};
