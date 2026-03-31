<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Mail\PasswordChangedMail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

final class PasswordController
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/forgot-password",
     *     tags={"🔐 Authentification"},
     *     summary="Mot de passe oublié",
     *     operationId="forgotPassword",
     *
     *     @OA\Response(response=200, description="Lien envoyé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'Si cette adresse est enregistrée, un email de réinitialisation a été envoyé.']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/reset-password",
     *     tags={"🔐 Authentification"},
     *     summary="Réinitialiser le mot de passe",
     *     operationId="resetPassword",
     *
     *     @OA\Response(response=200, description="Mot de passe réinitialisé"),
     *     @OA\Response(response=422, description="Token invalide")
     * )
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));

                $user->save();

                $user->tokens()->delete();

                Mail::to($user->email, $user->firstname)
                    ->queue(new PasswordChangedMail($user->email, $user->firstname));

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/update-password",
     *     tags={"🔐 Authentification"},
     *     summary="Mettre à jour le mot de passe (connecté)",
     *     operationId="updatePassword",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="Mot de passe mis à jour"),
     *     @OA\Response(response=422, description="Ancien mot de passe incorrect")
     * )
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => ['required', 'confirmed', 'different:current_password', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Le mot de passe actuel est incorrect.'], 422);
        }

        $user->fill([
            'password' => $request->new_password,
        ])->save();

        $currentToken = $user->currentAccessToken();
        if ($currentToken !== null && method_exists($currentToken, 'getKey')) { // @phpstan-ignore notIdentical.alwaysTrue, function.alreadyNarrowedType
            $user->tokens()->where('id', '!=', $currentToken->getKey())->delete();
        } else {
            $user->tokens()->delete();
        }

        Mail::to($user->email, $user->firstname)
            ->queue(new PasswordChangedMail($user->email, $user->firstname));

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }
}
