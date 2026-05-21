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
        Schema::table('ad', function (Blueprint $table): void {
            $table->jsonb('prescreening_questions')->nullable()->after('description');
        });

        Schema::table('tentative_reservations', function (Blueprint $table): void {
            $table->jsonb('prescreening_answers')->nullable()->after('client_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table): void {
            $table->dropColumn('prescreening_questions');
        });

        Schema::table('tentative_reservations', function (Blueprint $table): void {
            $table->dropColumn('prescreening_answers');
        });
    }
};
