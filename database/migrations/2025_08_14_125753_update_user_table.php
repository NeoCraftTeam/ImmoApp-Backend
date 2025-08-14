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
            $table->renameColumn('name', 'firstname');
            $table->string('lastname')->after('name');
            $table->string('avatar')->nullable()->after('lastname');
            $table->enum('type', ['Individual', 'agency'])->nullable()->after('avatar');
            $table->enum('role', ['customer', 'agent', 'admin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('firstname', 'name');
            $table->dropColumn('lastname');
            $table->dropColumn('role');
            $table->enum('type', ['Individual', 'agency'])->nullable()->after('avatar');
        });
    }
};
