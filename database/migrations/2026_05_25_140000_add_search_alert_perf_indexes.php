<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes identified in the May 2026 performance audit.
 *
 * Targets:
 *  - `search_alerts`: MatchSearchAlertsForAdJob scans the whole table on every
 *    new ad. Composite (is_active, city_id, type_id) enables DB-side pre-filter.
 *  - `search_alert_matches`: SendSearchAlertDigestJob queries (user_id, digest_sent_at).
 *  - `quarter`: city_id join used on every ad search.
 *  - `ad`: (quarter_id, status) for search-by-quarter filtering.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_alerts')) {
            $this->addIndexes('search_alerts', [
                ['sa_active_city_type_idx', ['is_active', 'city_id', 'type_id']],
                ['sa_active_user_idx', ['is_active', 'user_id']],
            ]);
        }

        if (Schema::hasTable('search_alert_matches')) {
            $this->addIndexes('search_alert_matches', [
                ['sam_user_digest_idx', ['user_id', 'digest_sent_at']],
                ['sam_alert_digest_idx', ['search_alert_id', 'digest_sent_at']],
            ]);
        }

        if (Schema::hasTable('quarter')) {
            $this->addIndexes('quarter', [
                ['quarter_city_id_idx', ['city_id']],
            ]);
        }

        if (Schema::hasTable('ad')) {
            $this->addIndexes('ad', [
                ['ad_quarter_status_idx', ['quarter_id', 'status']],
                ['ad_type_status_price_idx', ['type_id', 'status', 'price']],
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('search_alerts')) {
            $this->dropIndexes('search_alerts', [
                'sa_active_city_type_idx',
                'sa_active_user_idx',
            ]);
        }

        if (Schema::hasTable('search_alert_matches')) {
            $this->dropIndexes('search_alert_matches', [
                'sam_user_digest_idx',
                'sam_alert_digest_idx',
            ]);
        }

        if (Schema::hasTable('quarter')) {
            $this->dropIndexes('quarter', ['quarter_city_id_idx']);
        }

        if (Schema::hasTable('ad')) {
            $this->dropIndexes('ad', [
                'ad_quarter_status_idx',
                'ad_type_status_price_idx',
            ]);
        }
    }

    /**
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
