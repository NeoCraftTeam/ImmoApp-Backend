<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Filament's Export/Import models do not call HasUuids — they rely on the database
 * to auto-generate the primary key. On MySQL this works via auto-increment, but on
 * PostgreSQL a UUID column with no DEFAULT silently inserts NULL, triggering a
 * NOT NULL violation (SQLSTATE 23502).
 *
 * Fix: add gen_random_uuid() as the column default so PostgreSQL generates a UUID
 * whenever Filament inserts without providing one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE exports ALTER COLUMN id SET DEFAULT gen_random_uuid()');
        DB::statement('ALTER TABLE imports ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE exports ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE imports ALTER COLUMN id DROP DEFAULT');
    }
};
