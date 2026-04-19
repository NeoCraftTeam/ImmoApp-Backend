<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_id')->constrained('ad')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['active', 'archived', 'blocked'])->default('active');
            $table->timestamp('tenant_last_read_at')->nullable();
            $table->timestamp('landlord_last_read_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 200)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ad_id', 'tenant_id']);
            $table->index(['tenant_id', 'status', 'last_message_at']);
            $table->index(['landlord_id', 'status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
