<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * At most one pending or confirmed reservation per (client, ad).
 * Blocks parallel double-submit and accidental multi-slot bookings for the same listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "CREATE UNIQUE INDEX tr_unique_client_ad_active ON tentative_reservations (ad_id, client_id) WHERE status IN ('pending', 'confirmed')"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tr_unique_client_ad_active');
    }
};
