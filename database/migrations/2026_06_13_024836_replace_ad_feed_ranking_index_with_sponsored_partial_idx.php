<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces `idx_ad_feed_ranking` with a partial covering index whose
 * column order actually matches the feed's `orderBySponsorship` scope.
 *
 * Why:
 * The previous index `(is_subscription_sponsored, boost_score,
 * created_at, status, is_visible)` placed the equality-tested filter
 * columns (status, is_visible) AFTER the sort columns. Postgres treats
 * leading B-tree columns as sort keys, not predicates, so the planner
 * could not use the index to satisfy the feed's
 * `WHERE is_visible = true AND status IN (...) ORDER BY
 *  is_subscription_sponsored DESC, boost_score DESC, created_at DESC, id DESC`
 * query. Once sponsored rows became common, the feed fell back to a
 * sequential scan + sort over every visible+listed row.
 *
 * The replacement is a partial index — the WHERE clause inside the
 * index definition narrows the rowset to the only states the feed
 * actually queries, and the sort order matches the scope exactly.
 *
 * Uses raw SQL with `CREATE INDEX CONCURRENTLY` so the migration does
 * not lock writes on the `ad` table in production.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('ad', function ($table): void {
            $table->dropIndex('idx_ad_feed_ranking');
        });

        DB::statement(<<<'SQL'
            CREATE INDEX CONCURRENTLY IF NOT EXISTS ad_feed_sponsor_idx
            ON ad (
                is_subscription_sponsored DESC,
                boost_score DESC,
                created_at DESC,
                id DESC
            )
            WHERE is_visible = true
              AND status IN ('available', 'reserved')
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ad_feed_sponsor_idx');

        // `Schema::table->index(...)` issues `CREATE INDEX ... ON ad (...)`
        // inside a Schema lock, which blocks writes on the `ad` table for
        // the duration of the build (minutes on a hot production table).
        // The matching `up()` already uses CONCURRENTLY; match that here so
        // a rollback is also non-blocking. Requires `$withinTransaction
        // = false` (set above) — CONCURRENTLY cannot run inside a
        // transaction. `IF NOT EXISTS` makes the down() idempotent for
        // partial-rollback recovery.
        DB::statement(<<<'SQL'
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ad_feed_ranking
            ON ad (
                is_subscription_sponsored,
                boost_score,
                created_at,
                status,
                is_visible
            )
        SQL);
    }
};
