<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strips HTML tags from incoming request input to prevent stored XSS attacks.
 * Applied to fields that could later be rendered in API responses or frontend views.
 */
final class SanitizeInput
{
    /**
     * Fields that should have HTML tags stripped.
     *
     * @var list<string>
     */
    private const SANITIZE_FIELDS = [
        'comment',
        'description',
        'text',
        'title',
        'name',
        'bio',
        'address',
        'note',
        'notes',
        'message',
        'subject',
        'conditions',
        'reason',
    ];

    /**
     * Fields that should never be sanitized.
     *
     * @var list<string>
     */
    private const EXEMPT_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'email',
        'token',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        $request->merge($this->sanitize($input));

        return $next($request);
    }

    /**
     * Recursively sanitize input array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data, string $parentKey = ''): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitize($value, (string) $key);

                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            if (in_array((string) $key, self::EXEMPT_FIELDS, true)) {
                continue;
            }

            if ($this->shouldSanitize((string) $key, $parentKey)) {
                $data[$key] = strip_tags($value);
            }
        }

        return $data;
    }

    private function shouldSanitize(string $key, string $parentKey): bool
    {
        return in_array($key, self::SANITIZE_FIELDS, true)
            || in_array($parentKey, self::SANITIZE_FIELDS, true);
    }
}
