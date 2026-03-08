<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')
            ->where('payment_method', 'fedapay')
            ->update(['payment_method' => 'mobile_money']);
    }

    public function down(): void
    {
        DB::table('payments')
            ->where('payment_method', 'mobile_money')
            ->update(['payment_method' => 'fedapay']);
    }
};
