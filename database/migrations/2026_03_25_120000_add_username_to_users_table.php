<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 64)->nullable()->unique()->after('lastname');
            $table->string('bio', 500)->nullable()->after('username');
        });

        // Backfill existing users
        DB::table('users')->orderBy('created_at')->each(function ($user): void {
            $base = Str::slug(trim(($user->firstname ?? '').' '.($user->lastname ?? '')));
            if (empty($base)) {
                $base = 'user';
            }
            $candidate = $base;
            $i = 2;
            while (DB::table('users')->where('username', $candidate)->where('id', '!=', $user->id)->exists()) {
                $candidate = $base.'-'.$i;
                $i++;
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['username', 'bio']);
        });
    }
};
