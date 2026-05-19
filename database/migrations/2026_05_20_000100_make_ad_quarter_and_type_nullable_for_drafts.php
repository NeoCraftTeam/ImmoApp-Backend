<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draft creation only requires `title` at first; `quarter_id` and `type_id` are
 * filled in later steps. Without nullable FK columns Postgres returns 23502 on
 * POST /api/v1/ads?is_draft=1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad', function (Blueprint $table): void {
            $table->dropForeign(['quarter_id']);
            $table->dropForeign(['type_id']);
        });

        Schema::table('ad', function (Blueprint $table): void {
            $table->foreignUuid('quarter_id')->nullable()->change();
            $table->foreignUuid('type_id')->nullable()->change();
        });

        Schema::table('ad', function (Blueprint $table): void {
            $table->foreign('quarter_id')->references('id')->on('quarter')->cascadeOnDelete();
            $table->foreign('type_id')->references('id')->on('ad_type')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Intentionally left blank: existing draft rows may have NULL quarter/type.
    }
};
