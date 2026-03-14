<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_id')->constrained('ad')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('tenant_last_read_at')->nullable();
            $table->timestamp('landlord_last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['ad_id', 'tenant_id']);
            $table->index(['tenant_id', 'updated_at']);
            $table->index(['landlord_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
