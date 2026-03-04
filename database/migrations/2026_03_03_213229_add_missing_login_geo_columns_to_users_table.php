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
            if (!Schema::hasColumn('users', 'last_login_country')) {
                $table->string('last_login_country', 5)
                    ->nullable()
                    ->after('last_login_ip')
                    ->comment('ISO-3166-1 alpha-2 or CF-IPCountry value');
            }
            if (!Schema::hasColumn('users', 'last_login_city')) {
                $table->string('last_login_city')
                    ->nullable()
                    ->after('last_login_country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_country', 'last_login_city']);
        });
    }
};
