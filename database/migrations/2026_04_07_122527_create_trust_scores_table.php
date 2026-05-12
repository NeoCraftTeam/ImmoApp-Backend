<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_scores', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_context', 20); // 'tenant' or 'landlord'
            $table->unsignedSmallInteger('score')->default(0);
            $table->string('tier', 20)->default('non_verifie');
            $table->jsonb('components')->default('{}');
            $table->timestamp('computed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'role_context']);
            $table->index(['tier', 'role_context']);
            $table->index('score');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('trust_score_consent')->nullable()->after('onboarding_completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_scores');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('trust_score_consent');
        });
    }
};
