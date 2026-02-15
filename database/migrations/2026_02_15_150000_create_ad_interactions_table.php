<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('ad_id')->nullable()->constrained('ad')->nullOnDelete();
            $table->string('type', 20); // view, favorite, unfavorite, search, unlock
            $table->jsonb('metadata')->nullable(); // search filters, time spent, etc.
            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast querying
            $table->index(['user_id', 'type', 'created_at']);
            $table->index(['ad_id', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_interactions');
    }
};
