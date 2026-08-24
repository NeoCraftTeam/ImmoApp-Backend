<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Index deduplication — drop btree indexes whose columns are a strict leftmost
 * prefix of a wider composite that already exists. Redundant prefix indexes add
 * pure write amplification (every INSERT/UPDATE maintains them) with no read
 * benefit the superset can't serve.
 *
 * Successive audit migrations each added their own `(user_id, status)` /
 * `(status, created_at)` index without dropping the earlier one, leaving exact
 * and prefix duplicates:
 *
 *   payments:
 *     - payments_user_id_status_index  (user_id, status)  ← create_payments
 *     - payments_user_status_idx       (user_id, status)  ← audit 2026-02-12
 *     - payments_user_status_index     (user_id, status)  ← perf 2026-03-23
 *       all three are the leftmost prefix of the kept superset
 *       payments_user_status_created_idx (user_id, status, created_at).
 *
 *   ad:
 *     - ad_status_created_at_idx  (status, created_at)  ← audit 2026-02-12
 *       exact duplicate of the kept ad_status_created_idx (status, created_at).
 *     - ad_user_status_idx        (user_id, status)     ← global-scale 2026-05-02
 *       leftmost prefix of the kept ad_owner_listing_idx
 *       (user_id, status, created_at).
 *
 * Postgres-only. Idempotent (IF EXISTS / IF NOT EXISTS) and run CONCURRENTLY so
 * hot tables (`payments`, `ad`) are never write-locked.
 */
return new class extends Migration
{
    /**
     * CONCURRENTLY index operations cannot run inside a transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS payments_user_id_status_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS payments_user_status_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS payments_user_status_index');

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ad_status_created_at_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ad_user_status_idx');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS payments_user_id_status_index ON payments (user_id, status)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS payments_user_status_idx ON payments (user_id, status)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS payments_user_status_index ON payments (user_id, status)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ad_status_created_at_idx ON ad (status, created_at)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ad_user_status_idx ON ad (user_id, status)');
    }
};
