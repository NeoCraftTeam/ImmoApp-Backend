<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `GET /payments/history` filtre `WHERE user_id = ?` et trie
 * `ORDER BY created_at DESC` — sans index composite, Postgres retombe
 * sur l'index (user_id, status) puis trie en mémoire, coût croissant
 * avec l'historique. Cet index couvre exactement la requête.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'payments_user_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_user_id_created_at_index');
        });
    }
};
