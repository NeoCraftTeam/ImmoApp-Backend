<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class EmailVerificationController
{
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer',
            'hash' => 'required|string',
            'signature' => 'required|string',
            'expires' => 'required|integer',
        ]);

        // Vérifier que la signature n'a pas expiré
        if ($request->expires < now()->timestamp) {
            return response()->json([
                'message' => 'Le lien de vérification a expiré'
            ], 422);
        }

        $user = User::findOrFail($request->id);

        // Vérifier le hash
        if (!hash_equals($request->hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Lien de vérification invalide'
            ], 422);
        }

        // Vérifier la signature
        $payload = $request->id . $request->hash . $request->expires;
        $expectedSignature = hash_hmac('sha256', $payload, config('app.key'));

        if (!hash_equals($request->signature, $expectedSignature)) {
            return response()->json([
                'message' => 'Signature invalide'
            ], 422);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email déjà vérifié'
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email vérifié avec succès'
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email déjà vérifié'
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Lien de vérification envoyé'
        ]);
    }
}
