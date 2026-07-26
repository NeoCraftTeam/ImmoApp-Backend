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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->after('starts_at');
            $table->timestamp('renewed_at')->nullable()->after('cancelled_at');
            $table->string('previous_plan_id')->nullable()->after('subscription_plan_id');
            $table->integer('renewal_count')->default(0)->after('auto_renew');

            $table->index('trial_ends_at');
            $table->index('renewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['trial_ends_at']);
            $table->dropIndex(['renewed_at']);

            $table->dropColumn([
                'trial_ends_at',
                'renewed_at',
                'previous_plan_id',
                'renewal_count',
            ]);
        });
    }
};
