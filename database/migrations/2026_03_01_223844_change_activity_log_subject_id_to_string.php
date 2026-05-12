<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Change subject_id from uuid to varchar(36) to support both UUID and integer PKs.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE varchar(36) USING subject_id::text');
    }

    /**
     * Reverse: change back to uuid type.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE uuid USING subject_id::uuid');
    }
};
