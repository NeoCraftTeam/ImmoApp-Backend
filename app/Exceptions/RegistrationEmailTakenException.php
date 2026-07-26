<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

final class RegistrationEmailTakenException extends \RuntimeException implements Responsable
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Un SEUL message générique quel que soit l'état du compte existant
     * (SSO/Google, non vérifié, mot de passe). OWASP Authentication Cheat
     * Sheet — « Account creation » : ne pas créer de facteur de
     * distinction qui permettrait d'énumérer les comptes ou de révéler
     * le fournisseur de connexion. Le paramètre `User` est conservé pour
     * un éventuel logging serveur, mais n'influence plus la réponse.
     */
    public static function forExistingUser(User $user): self
    {
        return new self(__('auth.registration_generic_conflict'));
    }

    public function toResponse($request): Response
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'email' => [$this->getMessage()],
            ],
        ], 422);
    }
}
