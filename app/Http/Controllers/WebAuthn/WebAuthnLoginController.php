<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;

use function request;
use function response;

class WebAuthnLoginController
{
    /**
     * Returns the challenge options for assertion.
     *
     * We wrap the Laragear Responsable so we can inject the one-time challenge
     * token (`_wt`) into the JSON body. The Filament Alpine.js reads this token
     * and sends it back on the /login request (as a body field or header).
     * This lets CacheChallengeRepository find the stored challenge without
     * relying on the session ID, which can rotate between the two requests.
     */
    public function options(AssertionRequest $request): JsonResponse
    {
        $responsable = $request->toVerify($request->validate(['email' => 'sometimes|email|string']));
        $inner = $responsable->toResponse($request);

        /** @var array<string, mixed> $options */
        $options = json_decode((string) $inner->getContent(), true) ?? [];

        $token = request()->attributes->get('webauthn_challenge_token', '');
        $options['_wt'] = $token;

        return response()->json($options)
            ->header('X-WebAuthn-Token', $token);
    }

    /**
     * Log the user in.
     */
    public function login(AssertedRequest $request): Response
    {
        try {
            $user = $request->login();
            Log::debug('[WebAuthn] login() result', ['user_class' => $user ? $user::class : null, 'null' => is_null($user)]);

            return response()->noContent($user ? 204 : 422);
        } catch (\Throwable $e) {
            Log::warning('[WebAuthn] login() exception', [
                'class' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
            throw $e;
        }
    }
}
