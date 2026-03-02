<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adapt push_subscriptions for the laravel-notification-channels/webpush package.
     *
     * Changes:
     * - Replace user_id FK with polymorphic subscribable (type + id)
     * - Rename p256dh_key → public_key
     * - Make public_key, auth_token, content_encoding nullable (per package schema)
     */
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'endpoint']);
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->string('subscribable_type')->after('id')->default('');
            $table->string('subscribable_id', 36)->after('subscribable_type')->default('');
            $table->renameColumn('p256dh_key', 'public_key');
        });

        DB::table('push_subscriptions')->update([
            'subscribable_type' => 'App\\Models\\User',
            'subscribable_id' => DB::raw('user_id'),
        ]);

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->string('public_key')->nullable()->change();
            $table->string('auth_token')->nullable()->change();
            $table->string('content_encoding')->nullable()->change();
            $table->index(['subscribable_type', 'subscribable_id'], 'push_subscriptions_subscribable_morph_idx');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->foreignUuid('user_id')->after('id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::table('push_subscriptions')->update([
            'user_id' => DB::raw('subscribable_id'),
        ]);

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropIndex('push_subscriptions_subscribable_morph_idx');
            $table->dropColumn(['subscribable_type', 'subscribable_id']);
            $table->renameColumn('public_key', 'p256dh_key');
            $table->string('p256dh_key')->nullable(false)->change();
            $table->string('auth_token')->nullable(false)->change();
            $table->string('content_encoding')->default('aesgcm')->nullable(false)->change();
            $table->index(['user_id', 'endpoint']);
        });
    }
};
