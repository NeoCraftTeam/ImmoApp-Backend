<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SuccessCode;
use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * Return a successful JSON response.
     *
     * @param  array<string, mixed>|null  $data
     */
    public static function success(string $message, ?array $data = null, int $status = 200, ?string $code = null): JsonResponse
    {
        $safeMessage = SafeApiMessage::sanitize($message, $status);
        $payload = ['success' => true, 'message' => $safeMessage];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a successful JSON response from a centralized success code.
     *
     * The localized message is resolved from the {@see SuccessCode} enum and
     * the machine-stable code is emitted in the `code` field of the envelope.
     *
     * @param  array<string, mixed>|null  $data
     */
    public static function successCode(SuccessCode $code, ?array $data = null, int $status = 200): JsonResponse
    {
        return self::success($code->message(), $data, $status, $code->value);
    }

    /**
     * Return an error JSON response.
     *
     * @param  array<string, mixed>|null  $errors
     */
    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $envelope = SafeApiMessage::envelope($message, null, $status, null, $errors);
        $payload = ['success' => false, ...$envelope];

        return response()->json($payload, $status);
    }

    /**
     * Return a validation error response.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validationError(string $message, array $errors): JsonResponse
    {
        $envelope = SafeApiMessage::envelope($message, null, 422, null, $errors);

        return response()->json([
            'success' => false,
            ...$envelope,
        ], 422);
    }
}
