<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_signature_requests', function (Blueprint $table): void {
            $table->unsignedInteger('sign_otp_expires_unix')->nullable()->after('sign_otp_hash');
        });
    }

    public function down(): void
    {
        Schema::table('lease_signature_requests', function (Blueprint $table): void {
            $table->dropColumn('sign_otp_expires_unix');
        });
    }
};
