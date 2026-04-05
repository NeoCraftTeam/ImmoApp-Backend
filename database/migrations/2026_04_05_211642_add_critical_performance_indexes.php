<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ad: status index for draft/pending/available filtering
        Schema::table('ad', function (Blueprint $table) {
            $table->index('status', 'ad_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table) {
            $table->dropIndex('ad_status_index');
        });
    }
};
