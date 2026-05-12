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
            $table->boolean('is_verified')->default(false)->after('is_boosted');
            $table->timestamp('verified_at')->nullable()->after('is_verified');
            $table->string('verification_status')->default('none')->after('verified_at');
            $table->text('verification_notes')->nullable()->after('verification_status');
            $table->timestamp('verification_requested_at')->nullable()->after('verification_notes');
        });
    }

    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table): void {
            $table->dropColumn([
                'is_verified',
                'verified_at',
                'verification_status',
                'verification_notes',
                'verification_requested_at',
            ]);
        });
    }
};
