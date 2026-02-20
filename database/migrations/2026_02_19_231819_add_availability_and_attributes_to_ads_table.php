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
        Schema::table('ad', function (Blueprint $table) {
            // Task 4: Ad visibility management by owner
            $table->boolean('is_visible')->default(true)->after('status');

            // Task 5: Ad availability settings
            $table->date('available_from')->nullable()->after('is_visible');
            $table->date('available_to')->nullable()->after('available_from');

            // Task 6: Property attributes (JSON for flexibility)
            $table->json('attributes')->nullable()->after('available_to');

            // Index for visibility filtering
            $table->index('is_visible');
            $table->index(['available_from', 'available_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropIndex(['is_visible']);
            $table->dropIndex(['available_from', 'available_to']);
            $table->dropColumn(['is_visible', 'available_from', 'available_to', 'attributes']);
        });
    }
};
