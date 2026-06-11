<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forensic + quota log for the natural-language search endpoint.
 *
 * Each row records who made the call, the cleaned query, which provider
 * answered (or 'regex' / 'cache'), latency, and the parsed JSON. Rows are
 * Prunable with a 30-day retention via the App\Models\NlpSearchLog model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nlp_search_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('ip', 45)->nullable()->index();
            $table->string('context', 16)->default('customer'); // customer | owner
            $table->string('query', 320);
            $table->string('display_currency', 8)->nullable();
            $table->string('success_provider', 32)->nullable(); // groq | openai | gemini | … | regex | cache | failed
            $table->jsonb('parsed')->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nlp_search_logs');
    }
};
