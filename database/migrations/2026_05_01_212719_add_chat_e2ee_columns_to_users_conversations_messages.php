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
            $table->text('chat_e2ee_public_key_pem')->nullable();
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->text('e2ee_wrapped_key_tenant')->nullable();
            $table->text('e2ee_wrapped_key_landlord')->nullable();
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->boolean('is_client_sealed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn('is_client_sealed');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['e2ee_wrapped_key_tenant', 'e2ee_wrapped_key_landlord']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('chat_e2ee_public_key_pem');
        });
    }
};
