<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_ad_private_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_id')->constrained('ad')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_property_owner')->default(true);
            $table->text('owner_name')->nullable();
            $table->text('owner_address')->nullable();
            $table->text('owner_phone')->nullable();
            $table->text('owner_email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['ad_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_ad_private_notes');
    }
};
