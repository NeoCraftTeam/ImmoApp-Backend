<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Reçoit une URL signée temporaire depuis clerkExchange() et connecte
 * l'utilisateur en session web avant de le rediriger vers le bon panel Filament.
 */
class PanelSsoController
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Lien SSO invalide ou expiré.');
        }

        /** @var User|null $user */
        $user = User::find($request->query('user_id'));

        if ($user === null) {
            abort(404);
        }

        Auth::guard('web')->login($user, remember: true);

        return redirect($this->panelPath($user));
    }

    private function panelPath(User $user): string
    {
        if ($user->role === UserRole::ADMIN) {
            return '/admin';
        }

        if ($user->role === UserRole::AGENT) {
            return $user->type === UserType::AGENCY ? '/agency' : '/owner';
        }

        // Sécurité : si un customer arrive ici par erreur
        return '/';
    }
}
