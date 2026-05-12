<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table): void {
            $table->string('utm_content', 255)->nullable();
            $table->string('utm_term', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table): void {
            $table->dropColumn(['utm_content', 'utm_term']);
        });
    }
};
