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
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('answer');
        });

        Schema::table('anonymous_survey_responses', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropColumn('viewed_at');
        });

        Schema::table('anonymous_survey_responses', function (Blueprint $table) {
            $table->dropColumn('viewed_at');
        });
    }
};
