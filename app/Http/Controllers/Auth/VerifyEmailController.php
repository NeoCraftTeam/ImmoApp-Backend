<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class VerifyEmailController
{
    public function __invoke(Request $request, $id, $hash)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Le lien de confirmation a expiré ou la signature est invalide.');
        }

        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1((string) $user->getEmailForVerification()))) {
            abort(403, 'Lien de vérification invalide.');
        }

        if ($user->hasVerifiedEmail()) {
            return $this->showSuccessPage($user);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->showSuccessPage($user);
    }

    protected function showSuccessPage(User $user)
    {
        // Déterminer l'URL de redirection en fonction du type d'utilisateur
        $loginUrl = url('/admin/login');

        if ($user->type === UserType::AGENCY) {
            $loginUrl = url('/agency/login');
        } elseif ($user->type === UserType::INDIVIDUAL) {
            $loginUrl = url('/bailleur/login');
        }

        return view('auth.verified', [
            'loginUrl' => $loginUrl,
        ]);
    }
}
