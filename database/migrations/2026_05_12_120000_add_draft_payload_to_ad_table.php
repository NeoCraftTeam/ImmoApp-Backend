<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad', function (Blueprint $table): void {
            $table->jsonb('draft_payload')->nullable()->after('tour_published_at');
        });
    }

    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table): void {
            $table->dropColumn('draft_payload');
        });
    }
};
