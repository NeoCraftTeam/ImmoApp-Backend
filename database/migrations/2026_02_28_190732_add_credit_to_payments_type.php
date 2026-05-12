<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL stores an implicit check constraint from the original enum definition.
        // Drop it if present, then the string column already allows any value.
        $constraintName = 'payments_type_check';

        DB::statement("
            ALTER TABLE payments
            DROP CONSTRAINT IF EXISTS \"{$constraintName}\"
        ");
    }

    public function down(): void
    {
        // Restore the constraint without 'credit'
        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT \"payments_type_check\"
            CHECK (type IN ('unlock', 'boost', 'subscription'))
        ");
    }
};
