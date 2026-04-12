<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cast push_subscriptions.subscribable_id from varchar(36) to uuid.
     *
     * The column was created as string(36) with a '' default but users.id is
     * the native PostgreSQL uuid type. PostgreSQL refuses implicit varchar = uuid
     * comparisons, causing SQLSTATE[42883] in whereHas('pushSubscriptions').
     *
     * Steps:
     *   1. Drop the empty-string default (incompatible with uuid type)
     *   2. Delete any rows that have the placeholder '' default (invalid UUIDs)
     *   3. Cast the column to uuid
     */
    public function up(): void
    {
        // 1. Drop the default so the USING cast succeeds
        DB::statement('ALTER TABLE push_subscriptions ALTER COLUMN subscribable_id DROP DEFAULT');

        // 2. Remove rows with empty/invalid subscribable_id (legacy placeholder rows)
        DB::statement("DELETE FROM push_subscriptions WHERE subscribable_id = '' OR subscribable_id !~ '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'");

        // 3. Cast to native uuid type
        DB::statement(
            'ALTER TABLE push_subscriptions
             ALTER COLUMN subscribable_id TYPE uuid
             USING subscribable_id::uuid'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE push_subscriptions
             ALTER COLUMN subscribable_id TYPE varchar(36)
             USING subscribable_id::varchar'
        );
        DB::statement("ALTER TABLE push_subscriptions ALTER COLUMN subscribable_id SET DEFAULT ''");
    }
};
