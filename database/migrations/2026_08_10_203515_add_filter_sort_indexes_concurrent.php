<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performance audit — index the two non-FK columns that carry real query cost.
 *
 * A code-wide analysis of Eloquent filters / sorts confirmed the schema is
 * already well indexed. Only two gaps justified the write overhead:
 *
 *  1. `ad (status, transaction_type, created_at)` — the public search endpoint
 *     (AdSearchController) filters on `status` then narrows by
 *     `transaction_type` and sorts by `created_at`. This composite lets the
 *     planner serve the WHERE and the ORDER BY from a single index on the
 *     hottest read path. Leading with `status` mirrors the existing feed
 *     indexes so the column stays useful on its own.
 *
 *  2. `reviews (rating)` — the admin quality dashboard runs range counts
 *     (`rating >= 4`, `rating <= 2`) for the NPS metric on every cache miss.
 *
 * Postgres-only. Idempotent and built CONCURRENTLY to avoid write locks.
 */
return new class extends Migration
{
    /**
     * CONCURRENTLY index builds cannot run inside a transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS ad_status_transaction_type_created_idx '
            .'ON ad (status, transaction_type, created_at)'
        );

        DB::statement(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS reviews_rating_idx ON reviews (rating)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS reviews_rating_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ad_status_transaction_type_created_idx');
    }
};
