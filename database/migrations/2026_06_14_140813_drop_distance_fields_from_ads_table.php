<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the five user-typed proximity columns from the ad table.
 *
 * The proximity chips on the ad-detail page now source from
 * NeighborhoodScorecardService (Overpass POIs + ORS walking matrix)
 * via `/api/v1/ads/{ad}/keyscore` and `/neighborhood-scorecard`,
 * which are authoritative server-computed values. The columns were
 * a guess-at-best owner input that shadowed the scorecard's six
 * categories without aligning to them.
 *
 * Down-migration restores the column shapes (unsignedSmallInteger,
 * nullable, after `longitude`) but does NOT recover historical
 * values — anyone rolling back is expected to re-run the scorecard
 * for affected ads on demand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad', function (Blueprint $table): void {
            $table->dropColumn([
                'distance_main_road_m',
                'distance_shops_m',
                'distance_transport_m',
                'distance_school_m',
                'distance_hospital_m',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table): void {
            $table->unsignedSmallInteger('distance_main_road_m')->nullable()->after('longitude');
            $table->unsignedSmallInteger('distance_shops_m')->nullable()->after('distance_main_road_m');
            $table->unsignedSmallInteger('distance_transport_m')->nullable()->after('distance_shops_m');
            $table->unsignedSmallInteger('distance_school_m')->nullable()->after('distance_transport_m');
            $table->unsignedSmallInteger('distance_hospital_m')->nullable()->after('distance_school_m');
        });
    }
};
