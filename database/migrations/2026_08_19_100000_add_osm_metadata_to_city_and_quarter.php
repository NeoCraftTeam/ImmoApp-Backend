<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        }

        Schema::table('city', function (Blueprint $table): void {
            $table->char('country_code', 2)->nullable()->after('country');
            $table->string('admin_area')->nullable()->after('country_code');
            $table->string('display_name')->nullable()->after('name');
            $table->string('normalized_name')->nullable()->after('display_name');
            $table->string('osm_type', 16)->nullable()->after('location');
            $table->unsignedBigInteger('osm_id')->nullable()->after('osm_type');
            $table->string('place_type', 32)->nullable()->after('osm_id');
            $table->string('source', 24)->default('manual')->after('place_type');
            $table->timestampTz('osm_updated_at')->nullable()->after('source');
            $table->magellanMultiPolygon('boundary', 4326)->nullable()->after('location');
            $table->index(['country_code', 'normalized_name'], 'city_country_normalized_idx');
            $table->index(['osm_type', 'osm_id'], 'city_osm_lookup_idx');
            $table->spatialIndex('boundary', 'city_boundary_spatial_idx');
        });

        Schema::table('quarter', function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('name');
            $table->string('normalized_name')->nullable()->after('display_name');
            $table->string('osm_type', 16)->nullable()->after('location');
            $table->unsignedBigInteger('osm_id')->nullable()->after('osm_type');
            $table->string('place_type', 32)->nullable()->after('osm_id');
            $table->string('source', 24)->default('manual')->after('place_type');
            $table->timestampTz('osm_updated_at')->nullable()->after('source');
            $table->magellanMultiPolygon('boundary', 4326)->nullable()->after('location');
            $table->index(['city_id', 'normalized_name'], 'quarter_city_normalized_idx');
            $table->index(['osm_type', 'osm_id'], 'quarter_osm_lookup_idx');
            $table->spatialIndex('boundary', 'quarter_boundary_spatial_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX city_osm_unique_idx ON city (osm_type, osm_id)');
            DB::statement('CREATE UNIQUE INDEX quarter_osm_unique_idx ON quarter (osm_type, osm_id)');
            DB::statement('CREATE INDEX city_normalized_name_trgm_idx ON city USING gin (normalized_name gin_trgm_ops)');
            DB::statement('CREATE INDEX quarter_normalized_name_trgm_idx ON quarter USING gin (normalized_name gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS city_osm_unique_idx');
            DB::statement('DROP INDEX IF EXISTS quarter_osm_unique_idx');
            DB::statement('DROP INDEX IF EXISTS city_normalized_name_trgm_idx');
            DB::statement('DROP INDEX IF EXISTS quarter_normalized_name_trgm_idx');
        }

        Schema::table('quarter', function (Blueprint $table): void {
            $table->dropSpatialIndex('quarter_boundary_spatial_idx');
            $table->dropIndex('quarter_city_normalized_idx');
            $table->dropIndex('quarter_osm_lookup_idx');
            $table->dropColumn(['display_name', 'normalized_name', 'osm_type', 'osm_id', 'place_type', 'source', 'osm_updated_at', 'boundary']);
        });

        Schema::table('city', function (Blueprint $table): void {
            $table->dropSpatialIndex('city_boundary_spatial_idx');
            $table->dropIndex('city_country_normalized_idx');
            $table->dropIndex('city_osm_lookup_idx');
            $table->dropColumn(['country_code', 'admin_area', 'display_name', 'normalized_name', 'osm_type', 'osm_id', 'place_type', 'source', 'osm_updated_at', 'boundary']);
        });
    }
};
