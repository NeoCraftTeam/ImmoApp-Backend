<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lifecycle columns for lease contracts.
 *
 * Adds a status enum + termination metadata + an archive timestamp so the
 * owner can renew / terminate / archive a lease. Existing rows are
 * back-filled by comparing today() to lease_end so the dashboard counters
 * stay consistent after the migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_contracts', function (Blueprint $table): void {
            $table->string('status', 32)->default('active')->after('pdf_path');
            $table->timestamp('terminated_at')->nullable()->after('status');
            $table->string('termination_reason', 1000)->nullable()->after('terminated_at');
            $table->timestamp('archived_at')->nullable()->after('termination_reason');

            $table->index(['user_id', 'status']);
            $table->index(['status', 'lease_end']);
        });

        // Backfill: rows ending before today flip to expired so the
        // occupancy KPI matches reality immediately after deploy. Newer
        // rows stay active (the column default already does the right
        // thing for inserts that don't pass an explicit status).
        DB::table('lease_contracts')
            ->whereDate('lease_end', '<', now()->toDateString())
            ->update(['status' => 'expired']);
    }

    public function down(): void
    {
        Schema::table('lease_contracts', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'lease_end']);
            $table->dropColumn(['status', 'terminated_at', 'termination_reason', 'archived_at']);
        });
    }
};
