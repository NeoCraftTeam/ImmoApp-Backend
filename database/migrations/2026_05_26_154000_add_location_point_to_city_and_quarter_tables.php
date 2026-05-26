<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute une colonne location geometry(Point,4326) à city et quarter
 * (cohérent avec ad.location / clickbar/laravel-magellan).
 * Migre les données existantes latitude/longitude → location.
 * Les colonnes latitude/longitude décimales sont conservées en lecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('city', function (Blueprint $table): void {
            $table->magellanPoint('location', 4326)->nullable()->after('longitude');
            $table->spatialIndex('location', 'city_location_spatial_idx');
        });

        Schema::table('quarter', function (Blueprint $table): void {
            $table->magellanPoint('location', 4326)->nullable()->after('longitude');
            $table->spatialIndex('location', 'quarter_location_spatial_idx');
        });

        // Migrer les coordonnées existantes vers le champ geometry
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'UPDATE city SET location = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
                 WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND location IS NULL'
            );
            DB::statement(
                'UPDATE quarter SET location = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
                 WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND location IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::table('city', function (Blueprint $table): void {
            $table->dropSpatialIndex('city_location_spatial_idx');
            $table->dropColumn('location');
        });

        Schema::table('quarter', function (Blueprint $table): void {
            $table->dropSpatialIndex('quarter_location_spatial_idx');
            $table->dropColumn('location');
        });
    }
};
