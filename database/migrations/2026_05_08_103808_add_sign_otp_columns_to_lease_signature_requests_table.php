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
            $table->string('sign_otp_hash', 64)->nullable()->after('signature_hash');
            $table->timestampTz('sign_otp_expires_at')->nullable()->after('sign_otp_hash');
            $table->timestampTz('sign_otp_sent_at')->nullable()->after('sign_otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('lease_signature_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'sign_otp_hash',
                'sign_otp_expires_at',
                'sign_otp_sent_at',
            ]);
        });
    }
};
