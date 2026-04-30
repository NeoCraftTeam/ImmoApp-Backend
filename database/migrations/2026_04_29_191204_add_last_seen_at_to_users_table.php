<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Last activity heartbeat. Updated by the TouchLastSeen middleware
            // on authenticated requests (throttled to once per minute via cache).
            // Used by chat to display "Vu il y a 3 min" under offline users.
            $table->timestampTz('last_seen_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn('last_seen_at');
        });
    }
};
