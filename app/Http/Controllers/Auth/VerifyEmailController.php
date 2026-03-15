<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

final class VerifyEmailController
{
    public function __invoke(Request $request, $id, $hash)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Le lien de confirmation a expiré ou la signature est invalide.');
        }

        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1((string) $user->getEmailForVerification()))) {
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
        $loginUrl = $this->buildLoginUrl($user);

        return view('auth.verified', [
            'loginUrl' => $loginUrl,
            'isAdmin' => $user->role === \App\Enums\UserRole::ADMIN,
        ]);
    }

    protected function buildLoginUrl(User $user): string
    {
        if ($user->role === \App\Enums\UserRole::ADMIN) {
            $domain = config('filament.panels.admin_domain');
            if ($domain) {
                return 'https://'.$domain.'/login';
            }

            return url('/admin/login');
        }

        if ($user->type === UserType::AGENCY) {
            $domain = config('filament.panels.agency_domain');
            if ($domain) {
                return 'https://'.$domain.'/login';
            }

            return url('/agency/login');
        }

        if ($user->type === UserType::INDIVIDUAL) {
            $domain = config('filament.panels.owner_domain');
            if ($domain) {
                return 'https://'.$domain.'/login';
            }

            return url('/owner/login');
        }

        return url('/admin/login');
    }
}
