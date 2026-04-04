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
            $table->string('transaction_type', 20)
                ->nullable()
                ->default(null)
                ->after('type_id')
                ->comment('location | vente — type of transaction. Null = not specified.');
        });
    }

    public function down(): void
    {
        Schema::table('ad', function (Blueprint $table): void {
            $table->dropColumn('transaction_type');
        });
    }
};
