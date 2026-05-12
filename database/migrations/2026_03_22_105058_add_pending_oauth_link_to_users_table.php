<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_oauth_provider')->nullable()->after('locale');
            $table->string('pending_oauth_id')->nullable()->after('pending_oauth_provider');
            $table->string('pending_oauth_avatar')->nullable()->after('pending_oauth_id');
            $table->string('pending_oauth_token', 64)->nullable()->unique()->after('pending_oauth_avatar');
            $table->timestamp('pending_oauth_expires_at')->nullable()->after('pending_oauth_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pending_oauth_provider',
                'pending_oauth_id',
                'pending_oauth_avatar',
                'pending_oauth_token',
                'pending_oauth_expires_at',
            ]);
        });
    }
};
