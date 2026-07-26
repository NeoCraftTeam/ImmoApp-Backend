<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Requests\Chat\UpdateChatE2eeIdentityRequest;
use App\Support\ApiResponse;
use App\Support\ChatE2eeSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Register the user's RSA public key (PEM) for chat end-to-end encryption.
 * Private keys never leave the client.
 */
final readonly class ChatE2eeIdentityController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return response()->json([
            'public_key_pem' => $user->chat_e2ee_public_key_pem,
        ]);
    }

    public function update(UpdateChatE2eeIdentityRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        if (!ChatE2eeSchema::userPublicKeyColumnExists()) {
            return ApiResponse::error(
                'La base de données doit être migrée pour le chiffrement E2EE des messages. Exécutez : php artisan migrate',
                503,
            );
        }

        $user->forceFill([
            'chat_e2ee_public_key_pem' => $request->validated('public_key_pem'),
        ])->save();

        return response()->json(['ok' => true]);
    }
}
