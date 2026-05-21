<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_boosts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_id')->constrained('ad')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('boost_pack_id')->constrained('boost_packs');
            $table->integer('credits_spent');
            $table->integer('boost_score');
            $table->integer('duration_days');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->string('status')->default('active'); // active | expired | cancelled
            $table->timestamps();

            $table->index(['ad_id', 'status']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_boosts');
    }
};
