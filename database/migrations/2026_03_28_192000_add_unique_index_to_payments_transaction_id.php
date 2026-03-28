<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only add the unique index if it doesn't already exist
        if (!$this->hasUniqueIndex()) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->unique('transaction_id');
            });
        }
    }

    private function hasUniqueIndex(): bool
    {
        $indexes = Schema::getIndexes('payments');

        foreach ($indexes as $index) {
            if (
                in_array('transaction_id', $index['columns'], true)
                && $index['unique']
            ) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['transaction_id']);
        });
    }
};
