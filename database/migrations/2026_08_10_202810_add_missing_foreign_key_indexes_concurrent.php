<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performance audit — index every foreign key column that lacks a covering
 * index whose leading column is the FK itself.
 *
 * PostgreSQL auto-indexes primary keys and UNIQUE constraints but NEVER
 * foreign keys. Un-indexed FKs force a sequential scan of the child table on
 * every JOIN and, critically, on every parent DELETE / UPDATE that cascades
 * or restricts — a well-known source of lock contention and slow deletes.
 *
 * The columns below were identified by cross-referencing pg_constraint
 * (contype = 'f') against pg_index (indkey[0]) — i.e. FKs with no index that
 * starts with the FK column. Columns already covered by the leading edge of a
 * composite index are intentionally excluded (see the 2026_08_08 migration).
 *
 * Postgres-only. Idempotent (`IF NOT EXISTS`) and built CONCURRENTLY so the
 * index creation never blocks writes on production tables.
 */
return new class extends Migration
{
    /**
     * CONCURRENTLY index builds cannot run inside a transaction. Disabling the
     * implicit migration transaction lets each CREATE INDEX CONCURRENTLY run
     * without holding a write lock on the table.
     */
    public $withinTransaction = false;

    /**
     * Foreign-key columns to index, as [table, column] pairs.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const FOREIGN_KEYS = [
        ['ad_boosts', 'boost_pack_id'],
        ['ad_boosts', 'user_id'],
        ['ad_reports', 'owner_id'],
        ['ad_reports', 'resolved_by'],
        ['agency', 'owner_id'],
        ['anonymous_survey_answers', 'anonymous_response_id'],
        ['anonymous_survey_answers', 'survey_question_id'],
        ['conversations', 'last_message_id'],
        ['dispute_evidences', 'uploader_id'],
        ['dispute_messages', 'sender_id'],
        ['disputes', 'ad_id'],
        ['disputes', 'lease_id'],
        ['disputes', 'payment_id'],
        ['documents', 'user_id'],
        ['exports', 'user_id'],
        ['failed_import_rows', 'import_id'],
        ['imports', 'user_id'],
        ['invoices', 'agency_id'],
        ['invoices', 'payment_id'],
        ['lease_signature_audit_logs', 'user_id'],
        ['lease_signature_requests', 'lease_contract_id'],
        ['lease_signature_requests', 'requested_by'],
        ['newsletter_campaigns', 'created_by'],
        ['payments', 'agency_id'],
        ['point_transactions', 'ad_id'],
        ['promo_code_usages', 'payment_id'],
        ['promo_code_usages', 'user_id'],
        ['property_attributes', 'property_attribute_category_id'],
        ['refunds', 'processed_by'],
        ['reviews', 'agency_id'],
        ['search_alert_matches', 'ad_id'],
        ['site_visits', 'user_id'],
        ['socialite_users', 'user_id'],
        ['sponsored_impressions', 'user_id'],
        ['subscriptions', 'subscription_plan_id'],
        ['survey_questions', 'survey_id'],
        ['survey_responses', 'survey_id'],
        ['survey_responses', 'user_id'],
        ['team_invitations', 'agency_id'],
        ['team_invitations', 'invited_by'],
        ['tenant_screening_requests', 'requested_by'],
        ['tenant_screening_requests', 'reviewed_by'],
        ['tentative_reservations', 'appointment_schedule_id'],
        ['unlocked_ads', 'payment_id'],
        ['unlocked_ads', 'user_id'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::FOREIGN_KEYS as [$table, $column]) {
            $indexName = "{$table}_{$column}_fkidx";
            DB::statement(
                "CREATE INDEX CONCURRENTLY IF NOT EXISTS {$indexName} ON {$table} ({$column})"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::FOREIGN_KEYS as [$table, $column]) {
            $indexName = "{$table}_{$column}_fkidx";
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$indexName}");
        }
    }
};
