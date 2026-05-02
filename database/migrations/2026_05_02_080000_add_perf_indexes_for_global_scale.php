<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for slow / scale-sensitive queries identified during the
 * May 2026 enterprise audit. Composite indexes target the exact predicate
 * combinations used by:
 *
 *  - `Ad`: search list (status + type), recommendation candidate scan,
 *    public profile (user_id + status), boost ranking, hot facets.
 *  - `AdInteraction`: per-user recent profile build (PROFILE_DEPTH=30),
 *    favorites lookups, popularity counts (last 30 days).
 *  - `Payment`: gateway webhook lookup, history pagination.
 *  - `TentativeReservation`: tomorrow viewing reminders, owner dashboard.
 *  - `LeaseContract`: lease expiry scan, owner dashboard.
 *
 * Each `if-not-exists` guard via `hasIndex()` keeps the migration idempotent
 * across environments where partial indexes already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexes('ad', [
            ['ad_status_type_idx', ['status', 'type_id']],
            ['ad_user_status_idx', ['user_id', 'status']],
            ['ad_status_price_idx', ['status', 'price']],
            ['ad_status_created_idx', ['status', 'created_at']],
        ]);

        if (Schema::hasTable('ad_interactions')) {
            $this->addIndexes('ad_interactions', [
                ['ad_int_user_type_created_idx', ['user_id', 'type', 'created_at']],
                ['ad_int_ad_type_idx', ['ad_id', 'type']],
                ['ad_int_type_created_idx', ['type', 'created_at']],
            ]);
        }

        if (Schema::hasTable('payments')) {
            $this->addIndexes('payments', [
                ['payments_gateway_txref_idx', ['gateway', 'transaction_id']],
                ['payments_user_status_created_idx', ['user_id', 'status', 'created_at']],
                ['payments_status_created_idx', ['status', 'created_at']],
            ]);
        }

        if (Schema::hasTable('tentative_reservations')) {
            // `tr_ad_date_status_index` already exists (ad_id, slot_date, status).
            // `res_status_slot_idx` (status, slot_date) is the leftmost-column
            // counterpart — required by the daily viewing-reminder cron that
            // scans all `confirmed` reservations for tomorrow's date.
            $this->addIndexes('tentative_reservations', [
                ['res_status_slot_idx', ['status', 'slot_date']],
            ]);
        }

        if (Schema::hasTable('lease_contracts')) {
            // No `status` column — only `lease_end` matters for the expiry scan.
            $this->addIndexes('lease_contracts', [
                ['lease_end_idx', ['lease_end']],
            ]);
        }

        if (Schema::hasTable('login_histories')) {
            $this->addIndexes('login_histories', [
                ['login_user_success_created_idx', ['user_id', 'successful', 'created_at']],
            ]);
        }
    }

    public function down(): void
    {
        $this->dropIndexes('ad', [
            'ad_status_type_idx',
            'ad_user_status_idx',
            'ad_status_price_idx',
            'ad_status_created_idx',
        ]);

        if (Schema::hasTable('ad_interactions')) {
            $this->dropIndexes('ad_interactions', [
                'ad_int_user_type_created_idx',
                'ad_int_ad_type_idx',
                'ad_int_type_created_idx',
            ]);
        }

        if (Schema::hasTable('payments')) {
            $this->dropIndexes('payments', [
                'payments_gateway_txref_idx',
                'payments_user_status_created_idx',
                'payments_status_created_idx',
            ]);
        }

        if (Schema::hasTable('tentative_reservations')) {
            $this->dropIndexes('tentative_reservations', [
                'res_status_slot_idx',
            ]);
        }

        if (Schema::hasTable('lease_contracts')) {
            $this->dropIndexes('lease_contracts', [
                'lease_end_idx',
            ]);
        }

        if (Schema::hasTable('login_histories')) {
            $this->dropIndexes('login_histories', [
                'login_user_success_created_idx',
            ]);
        }
    }

    /**
     * Add a list of [name, columns] composite indexes, skipping any that already exist.
     *
     * @param  array<int, array{0: string, 1: array<int, string>}>  $indexes
     */
    private function addIndexes(string $table, array $indexes): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes): void {
            foreach ($indexes as [$name, $columns]) {
                if (!$this->indexExists($table, $name)) {
                    $blueprint->index($columns, $name);
                }
            }
        });
    }

    /**
     * @param  array<int, string>  $names
     */
    private function dropIndexes(string $table, array $names): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table, $names): void {
            foreach ($names as $name) {
                if ($this->indexExists($table, $name)) {
                    $blueprint->dropIndex($name);
                }
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
            [$table, $indexName],
        );

        return $rows !== [];
    }
};
