<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Add a unique constraint on point_transactions.payment_id (where not null) to
 * prevent double-crediting caused by concurrent webhook + verify-purchase calls.
 *
 * Before adding the constraint, any existing duplicate rows created by the race
 * condition are identified, the surplus rows deleted, and the affected users'
 * balances corrected. All changes are wrapped in a transaction so the migration
 * is atomic and rollback-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // 1. Find payment_ids credited more than once
            $duplicates = DB::table('point_transactions')
                ->select('payment_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(points) as total_points'))
                ->whereNotNull('payment_id')
                ->groupBy('payment_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $dup) {
                // Keep the earliest row; delete the rest
                $rows = DB::table('point_transactions')
                    ->where('payment_id', $dup->payment_id)
                    ->orderBy('created_at')
                    ->get(['id', 'user_id', 'points']);

                $keepRow = $rows->first();
                $surplus = $rows->skip(1); // every row after the first is a duplicate

                foreach ($surplus as $row) {
                    DB::table('point_transactions')->where('id', $row->id)->delete();

                    // Reverse the over-credited points on the user's balance
                    DB::table('users')
                        ->where('id', $row->user_id)
                        ->decrement('point_balance', abs($row->points));

                    Log::warning(
                        "[Migration] Duplicate credit removed: point_transaction {$row->id} "
                        ."(payment_id={$dup->payment_id}, user={$row->user_id}, points={$row->points}). "
                        .'Balance corrected.'
                    );
                }
            }

            // 2. Now safe to add the unique constraint
            Schema::table('point_transactions', function (Blueprint $table): void {
                $table->unique('payment_id', 'point_transactions_payment_id_unique');
            });
        });
    }

    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table): void {
            $table->dropUnique('point_transactions_payment_id_unique');
        });
    }
};
