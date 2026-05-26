<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes pour la recherche/filtrage géographique :
 *  - GIN trigram sur city.name + quarter.name → ilike rapide (autocomplete)
 *  - composite (country, name) sur city → tri par pays
 *  - GIST spatial sur city/quarter coordinates si lat/lng non null
 *  - Foreign key indexes manquants
 */
return new class extends Migration
{
    public function up(): void
    {
        // pg_trgm extension (idempotent)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            // Trigram indexes pour ilike '%query%' rapide
            DB::statement('CREATE INDEX IF NOT EXISTS city_name_trgm_idx ON city USING GIN (lower(name) gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS quarter_name_trgm_idx ON quarter USING GIN (lower(name) gin_trgm_ops)');
        }

        // Composite (country, name) pour le tri/filtre admin
        Schema::table('city', function ($table): void {
            $table->index(['country', 'name'], 'city_country_name_idx');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS city_name_trgm_idx');
            DB::statement('DROP INDEX IF EXISTS quarter_name_trgm_idx');
        }

        Schema::table('city', function ($table): void {
            $table->dropIndex('city_country_name_idx');
        });
    }
};
