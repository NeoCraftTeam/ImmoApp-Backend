<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_alerts', function (Blueprint $table): void {
            $table->boolean('notify_email')->default(true)->after('is_active');
            $table->boolean('notify_push')->default(true)->after('notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('search_alerts', function (Blueprint $table): void {
            $table->dropColumn(['notify_email', 'notify_push']);
        });
    }
};
