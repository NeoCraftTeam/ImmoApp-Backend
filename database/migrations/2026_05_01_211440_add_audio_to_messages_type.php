<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allow message type "audio" for voice notes (WhatsApp-style).
     *
     * The original messages migration used a PostgreSQL check constraint on `type`.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS messages_type_check');

        DB::statement(
            "ALTER TABLE messages ADD CONSTRAINT messages_type_check CHECK (type::text = ANY (ARRAY['text'::text, 'image'::text, 'file'::text, 'audio'::text, 'system'::text]))"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS messages_type_check');

        DB::statement(
            "ALTER TABLE messages ADD CONSTRAINT messages_type_check CHECK (type::text = ANY (ARRAY['text'::text, 'image'::text, 'file'::text, 'system'::text]))"
        );
    }
};
