<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\SafeApiMessage;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safety net that strips backend-internal detail from API error responses.
 *
 * Most error surfaces already route through {@see SafeApiMessage}, but ~200
 * ad-hoc `return response()->json([...], 4xx)` sites bypass it. Thrown
 * exceptions are handled safely in bootstrap/app.php; this After middleware
 * covers the controller-returned responses that never reach that handler.
 *
 * For any API JSON response with a 4xx/5xx status it replaces a sensitive
 * `message`/`hint`, filters sensitive `errors`, and drops raw debug keys when
 * APP_DEBUG is off. It is a no-op for already-safe payloads, so existing
 * envelopes pass through untouched.
 */
final class SanitizeApiErrorResponse
{
    /**
     * Introspection keys that must never reach a production client.
     *
     * @var string[]
     */
    private const array DEBUG_KEYS = ['debug', 'exception', 'trace', 'file', 'line'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$response instanceof JsonResponse || $response->getStatusCode() < 400) {
            return $response;
        }

        $data = $response->getData(true);

        if (!is_array($data)) {
            return $response;
        }

        $status = $response->getStatusCode();
        $changed = false;
        $sanitized = $this->sanitizePayload($data, $status, $changed);

        if ($changed) {
            Log::warning('Sanitized leaking API error response.', [
                'path' => $request->path(),
                'status' => $status,
            ]);
            $response->setData($sanitized);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $data, int $status, bool &$changed): array
    {
        if (isset($data['message']) && is_string($data['message']) && SafeApiMessage::isSensitive($data['message'])) {
            $data['message'] = SafeApiMessage::fallbackForStatus($status);
            $changed = true;
        }

        if (isset($data['hint']) && is_string($data['hint']) && SafeApiMessage::isSensitive($data['hint'])) {
            unset($data['hint']);
            $changed = true;
        }

        if (isset($data['errors']) && is_array($data['errors'])) {
            $data['errors'] = $this->sanitizeErrors($data['errors'], $changed);
        }

        if (isset($data['error']) && is_array($data['error'])) {
            $data['error'] = $this->sanitizePayload($data['error'], $status, $changed);
        }

        if (!config('app.debug')) {
            foreach (self::DEBUG_KEYS as $key) {
                if (array_key_exists($key, $data)) {
                    unset($data[$key]);
                    $changed = true;
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<string, mixed>
     */
    private function sanitizeErrors(array $errors, bool &$changed): array
    {
        foreach ($errors as $field => $messages) {
            $list = is_array($messages) ? $messages : [$messages];
            $safe = [];
            foreach ($list as $message) {
                if (is_string($message) && SafeApiMessage::isSensitive($message)) {
                    $changed = true;

                    continue;
                }
                $safe[] = $message;
            }

            if ($safe === []) {
                unset($errors[$field]);
                $changed = true;
            } else {
                $errors[$field] = $safe;
            }
        }

        return $errors;
    }
}
