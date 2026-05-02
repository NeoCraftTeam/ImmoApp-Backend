<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds per-user globalisation columns required for international rollout:
 *
 *   - `timezone`  — IANA TZ string (e.g. "Africa/Douala", "Europe/Paris").
 *                    When null, the resolver falls back to
 *                    config('locale.default_timezone_per_locale')[$user->locale].
 *   - `currency`  — ISO-4217 code, used to render prices and to pick the
 *                    default checkout currency. When null, the platform
 *                    default (`config('payment.default_currency')`) wins.
 *
 * `users.locale` already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('locale');
            }
            if (!Schema::hasColumn('users', 'currency')) {
                $table->string('currency', 8)->nullable()->after('timezone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('users', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
    }
};
