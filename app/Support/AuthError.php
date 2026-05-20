<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * User-facing auth error copy and machine codes.
 *
 * Public {@see message} values must never reveal role, panel name, or whether an email exists.
 * Use {@see code} for frontend routing only.
 */
final class AuthError
{
    public const string CODE_INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';

    public const string CODE_PANEL_ACCESS_DENIED = 'PANEL_ACCESS_DENIED';

    #[\Deprecated(message: 'Prefer {@see CODE_PANEL_ACCESS_DENIED}; kept for existing clients.')]
    public const string CODE_ROLE_CONTEXT_MISMATCH = 'ROLE_CONTEXT_MISMATCH';

    public const string CODE_TOKEN_ROLE_MISMATCH = 'TOKEN_ROLE_MISMATCH';

    public const string CODE_USER_ROLE_MISMATCH = 'USER_ROLE_MISMATCH';

    /** Wrong password, unknown email, failed CAPTCHA, or wrong login panel. */
    public const string LOGIN_FAILURE_MESSAGE = 'Identifiants incorrects';

    /** Authenticated user on an API route for the wrong interface. */
    public const string PANEL_UNAVAILABLE_MESSAGE = 'Cette interface n\'est pas disponible pour ce compte.';

    public const string TOKEN_CONTEXT_MESSAGE = 'Session non autorisée pour cette interface.';

    /**
     * @param  self::CODE_*|string  $code
     */
    public static function loginFailure(int $status = 401, string $code = self::CODE_INVALID_CREDENTIALS): JsonResponse
    {
        return response()->json([
            'message' => self::LOGIN_FAILURE_MESSAGE,
            'code' => $code,
        ], $status);
    }

    /**
     * @param  self::CODE_*|string  $code
     */
    public static function panelAccessDenied(int $status = 403, string $code = self::CODE_PANEL_ACCESS_DENIED): JsonResponse
    {
        return response()->json([
            'message' => self::PANEL_UNAVAILABLE_MESSAGE,
            'code' => $code,
        ], $status);
    }

    /**
     * @param  self::CODE_*|string  $code
     */
    public static function tokenContextDenied(int $status = 403, string $code = self::CODE_TOKEN_ROLE_MISMATCH): JsonResponse
    {
        return response()->json([
            'message' => self::TOKEN_CONTEXT_MESSAGE,
            'code' => $code,
        ], $status);
    }

    /**
     * Login-time panel mismatch (same public message as invalid credentials).
     *
     * @param  self::CODE_*|string  $code
     */
    public static function loginPanelMismatch(int $status = 401, string $code = self::CODE_PANEL_ACCESS_DENIED): JsonResponse
    {
        return self::loginFailure($status, $code);
    }

    /**
     * @param  self::CODE_*|string  $code
     * @return array{message: string, code: string}
     */
    public static function loginFailurePayload(string $code = self::CODE_INVALID_CREDENTIALS): array
    {
        return [
            'message' => self::LOGIN_FAILURE_MESSAGE,
            'code' => $code,
        ];
    }

    /**
     * @param  self::CODE_*|string  $code
     * @return array{message: string, code: string}
     */
    public static function panelAccessDeniedPayload(string $code = self::CODE_PANEL_ACCESS_DENIED): array
    {
        return [
            'message' => self::PANEL_UNAVAILABLE_MESSAGE,
            'code' => $code,
        ];
    }
}
