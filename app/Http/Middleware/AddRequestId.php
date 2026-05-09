<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AddRequestId
{
    private const int MAX_CORRELATION_LEN = 128;

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = self::normalizeIncomingRequestId($request->header('X-Request-ID'))
            ?? (string) Str::uuid();

        $request->headers->set('X-Request-ID', $requestId);

        $correlationId = self::normalizeIncomingCorrelationId($request->header('X-Correlation-ID'))
            ?? $requestId;

        Log::withContext([
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
        ]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }

    private static function normalizeIncomingRequestId(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $trimmed = trim($raw);
        if (strlen($trimmed) > 128) {
            return null;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $trimmed) === 1) {
            return $trimmed;
        }

        return null;
    }

    private static function normalizeIncomingCorrelationId(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $trimmed = trim($raw);
        if (strlen($trimmed) > self::MAX_CORRELATION_LEN) {
            return null;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $trimmed) === 1) {
            return $trimmed;
        }

        if (preg_match('/^[A-Za-z0-9._-]{8,64}$/', $trimmed) === 1) {
            return $trimmed;
        }

        return null;
    }
}
