<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mark all existing users as having completed onboarding.
 *
 * Only users created AFTER this migration will have
 * onboarding_completed_at = NULL, which triggers the welcome flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('onboarding_completed_at')
            ->update(['onboarding_completed_at' => now()]);
    }

    public function down(): void
    {
        // No-op: we can't know which users were genuinely new vs backfilled.
    }
};
