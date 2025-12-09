<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class EmailVerificationController
{
    public function verify(Request $request): JsonResponse|\Illuminate\View\View
    {
        $id = $request->route('id');
        $hash = $request->route('hash');

        // Vérifier que l'URL est valide et signée
        if (! URL::hasValidSignature($request)) {
            return view('email-verification-error', [
                'message' => 'Lien de vérification invalide ou expiré',
            ]);
        }

        $user = User::findOrFail($id);

        // Vérifier le hash
        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return view('email-verification-error', [
                'message' => 'Lien de vérification invalide',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return view('email-verified', [
                'message' => 'Email déjà vérifié',
                'user' => $user,
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return view('email-verified', [
            'message' => 'Email vérifié avec succès',
            'user' => $user,
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email déjà vérifié',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Lien de vérification envoyé',
        ]);
    }
}
