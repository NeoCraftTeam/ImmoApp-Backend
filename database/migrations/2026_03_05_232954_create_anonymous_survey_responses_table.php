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
        Schema::create('anonymous_survey_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained()->onDelete('cascade');
            // HMAC-SHA256 of the session ID — never the raw session ID.
            $table->string('session_token_hash', 64)->index();
            // SHA-256 of the visitor IP — never the raw IP.
            $table->string('ip_hash', 64)->nullable()->index();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['survey_id', 'session_token_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymous_survey_responses');
    }
};
