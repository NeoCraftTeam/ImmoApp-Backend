<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strips HTML tags from ALL incoming string request inputs to prevent stored XSS attacks.
 *
 * Uses a denylist approach (sanitize everything except exempted fields) rather than
 * an allowlist, so new fields are protected by default without requiring registration.
 * Recursively processes nested arrays.
 */
final class SanitizeInput
{
    /**
     * Fields that must never be modified — passwords, tokens, binary data, structured values.
     *
     * @var list<string>
     */
    private const array EXEMPT_FIELDS = [
        'password',
        'password_confirmation',
        'confirm_password',
        'current_password',
        'new_password',
        'email',
        'token',
        'access_token',
        'refresh_token',
        '_token',
        'signature',
        'otp',
        'code',
        'hash',
        'file',
        'avatar',
        'image',
        'photo',
        'document',
        'attachment',
        'lat',
        'lng',
        'latitude',
        'longitude',
        'location',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->merge($this->sanitize($request->all()));

        return $next($request);
    }

    /**
     * Recursively strip HTML tags from all string values not in the exempt list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitize($value);

                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            if (in_array((string) $key, self::EXEMPT_FIELDS, true)) {
                continue;
            }

            $data[$key] = strip_tags($value);
        }

        return $data;
    }
}
