<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('session_id', 64)->index();
            $table->string('source')->nullable();
            $table->string('referrer_domain')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_hash', 64);
            $table->string('device_type')->nullable();
            $table->timestamp('visited_at');
            $table->index(['visited_at', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
