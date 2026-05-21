<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AUTH-5 : Token family tracking.
 *
 * - family_id  : UUID propagé de token en token à chaque rotation.
 *                Tous les tokens issus du même login initial partagent ce family_id.
 *                Permet d'invalider toute la famille en cas de compromission.
 *
 * - revoked_at : Soft-revocation — au lieu de supprimer physiquement un token lors
 *                de la rotation, on le marque revoked_at = now(). Cela permet de
 *                détecter la réutilisation d'un token déjà révoqué (token volé
 *                réutilisé après rotation), signe d'une famille compromise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->uuid('family_id')->nullable()->index()->after('abilities');
            $table->timestamp('revoked_at')->nullable()->index()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn(['family_id', 'revoked_at']);
        });
    }
};
