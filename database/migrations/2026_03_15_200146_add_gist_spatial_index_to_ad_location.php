<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure GIST spatial index exists on ad.location for ST_DWithin/ST_Distance queries.
     * Magellan's spatialIndex may create a different index; this explicitly adds GIST.
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS ad_location_gist_idx ON ad USING GIST (location)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ad_location_gist_idx');
    }
};
