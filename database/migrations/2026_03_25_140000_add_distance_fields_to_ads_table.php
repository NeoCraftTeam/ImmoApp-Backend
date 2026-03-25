<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad', function (Blueprint $table): void {
            $table->unsignedSmallInteger('distance_main_road_m')->nullable()->after('longitude');
            $table->unsignedSmallInteger('distance_shops_m')->nullable()->after('distance_main_road_m');
            $table->unsignedSmallInteger('distance_transport_m')->nullable()->after('distance_shops_m');
            $table->unsignedSmallInteger('distance_school_m')->nullable()->after('distance_transport_m');
            $table->unsignedSmallInteger('distance_hospital_m')->nullable()->after('distance_school_m');
        });
    }

    public function down(): void
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
};
