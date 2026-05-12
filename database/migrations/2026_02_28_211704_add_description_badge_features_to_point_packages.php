<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('point_packages', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
            $table->string('badge')->nullable()->after('description');
            $table->json('features')->nullable()->after('points_awarded');
            $table->boolean('is_popular')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_packages', function (Blueprint $table) {
            $table->dropColumn(['description', 'badge', 'features', 'is_popular']);
        });
    }
};
