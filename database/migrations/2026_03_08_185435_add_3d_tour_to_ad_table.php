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
            if (!Schema::hasColumn('ad', 'has_3d_tour')) {
                $table->boolean('has_3d_tour')->default(false)->after('description');
            }
            if (!Schema::hasColumn('ad', 'tour_config')) {
                $table->json('tour_config')->nullable()->after('has_3d_tour');
            }
            if (!Schema::hasColumn('ad', 'tour_published_at')) {
                $table->timestamp('tour_published_at')->nullable()->after('tour_config');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropColumn(['has_3d_tour', 'tour_config', 'tour_published_at']);
        });
    }
};
