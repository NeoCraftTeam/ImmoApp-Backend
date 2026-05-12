<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends `users.bio` from VARCHAR(500) to VARCHAR(2000) to support a richer
 * markdown-style bio (bold/italic/bullets/headings) on the public profile.
 *
 * Owner-panel form requests + the public profile page display were already
 * raised to 2 000 chars in the same release; this migration aligns the
 * physical column so the validation can succeed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('bio', 2000)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('bio', 500)->nullable()->change();
        });
    }
};
