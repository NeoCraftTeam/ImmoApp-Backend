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
        Schema::table('anonymous_survey_responses', function (Blueprint $table): void {
            $table->string('respondent_audience', 32)->default('public_guest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anonymous_survey_responses', function (Blueprint $table): void {
            $table->dropColumn('respondent_audience');
        });
    }
};
