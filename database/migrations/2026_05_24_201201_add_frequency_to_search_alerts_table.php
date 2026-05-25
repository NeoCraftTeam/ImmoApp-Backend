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
        Schema::table('search_alerts', function (Blueprint $table) {
            $table->string('frequency', 20)->default('immediate')->after('notify_push');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('search_alerts', function (Blueprint $table) {
            $table->dropColumn('frequency');
        });
    }
};
