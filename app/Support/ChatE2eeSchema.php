<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Detects whether chat E2EE migrations have been applied on the current database.
 * When deploying behind running code, missing columns must not 500 the whole API.
 */
final class ChatE2eeSchema
{
    private static ?bool $userPublicKeyColumn = null;

    private static ?bool $messageClientSealedColumn = null;

    private static ?bool $conversationWrappedKeys = null;

    public static function userPublicKeyColumnExists(): bool
    {
        return self::$userPublicKeyColumn ??= Schema::hasColumn('users', 'chat_e2ee_public_key_pem');
    }

    public static function messageClientSealedColumnExists(): bool
    {
        return self::$messageClientSealedColumn ??= Schema::hasColumn('messages', 'is_client_sealed');
    }

    public static function conversationWrappedKeyColumnsExist(): bool
    {
        return self::$conversationWrappedKeys ??= Schema::hasColumn('conversations', 'e2ee_wrapped_key_tenant')
            && Schema::hasColumn('conversations', 'e2ee_wrapped_key_landlord');
    }

    /**
     * All E2EE-related columns from migration `2026_05_01_212719_add_chat_e2ee_*` are present.
     */
    public static function e2eeFullyMigrated(): bool
    {
        return self::userPublicKeyColumnExists()
            && self::messageClientSealedColumnExists()
            && self::conversationWrappedKeyColumnsExist();
    }

    /**
     * Columns to select when eager-loading tenant/landlord on conversation payloads.
     *
     * @return list<string>
     */
    public static function userParticipantSelectColumns(): array
    {
        $cols = ['id', 'firstname', 'lastname', 'avatar', 'last_seen_at'];
        if (self::userPublicKeyColumnExists()) {
            $cols[] = 'chat_e2ee_public_key_pem';
        }

        return $cols;
    }

    /**
     * Laravel eager-load path fragment: "tenant:id,firstname,...".
     */
    public static function userParticipantEagerLoadSpec(string $relation): string
    {
        return $relation.':'.implode(',', self::userParticipantSelectColumns());
    }

    /**
     * Columns for eager-loading reply previews on message history.
     *
     * @return list<string>
     */
    public static function messageReplyToSelectColumns(): array
    {
        $cols = ['id', 'body', 'body_iv', 'sender_id', 'deleted_at'];
        if (self::messageClientSealedColumnExists()) {
            $cols[] = 'is_client_sealed';
        }

        return $cols;
    }

    /** Laravel fragment: "replyTo:id,body,...". */
    public static function messageReplyToEagerLoadSpec(): string
    {
        return 'replyTo:'.implode(',', self::messageReplyToSelectColumns());
    }
}
