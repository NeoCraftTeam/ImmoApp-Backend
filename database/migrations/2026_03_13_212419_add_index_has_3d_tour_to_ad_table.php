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
            $table->index('has_3d_tour');
        });
    }

    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropIndex(['has_3d_tour']);
        });
    }
};
