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
        Schema::create('anonymous_survey_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('anonymous_response_id')
                ->constrained('anonymous_survey_responses')
                ->onDelete('cascade');
            $table->foreignUuid('survey_question_id')->constrained()->onDelete('cascade');
            $table->text('answer');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymous_survey_answers');
    }
};
