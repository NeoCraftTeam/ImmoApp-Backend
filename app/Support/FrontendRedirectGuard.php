<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validates absolute URLs used as post-payment redirects (Flutterwave callback_url).
 *
 * Host must match {@see config('app.frontend_url')} and optional
 * {@see config('app.oauth_allowed_redirect_hosts')} entries — same policy as OAuth redirects.
 */
final class FrontendRedirectGuard
{
    /**
     * @return list<string>
     */
    public static function allowedHosts(): array
    {
        $allowed = [];
        $frontendHost = parse_url((string) config('app.frontend_url', ''), PHP_URL_HOST);
        if (is_string($frontendHost) && $frontendHost !== '') {
            $allowed[] = mb_strtolower($frontendHost);
        }

        $extra = (string) config('app.oauth_allowed_redirect_hosts', '');
        foreach (array_filter(array_map(trim(...), explode(',', $extra))) as $h) {
            $allowed[] = mb_strtolower($h);
        }

        /** @var list<string> */
        return array_values(array_unique($allowed));
    }

    public static function isAllowedAbsoluteUrl(string $uri): bool
    {
        if ($uri === '' || strlen($uri) > 2048) {
            return false;
        }

        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = mb_strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        if ($scheme === 'http' && !app()->environment('local', 'testing')) {
            return false;
        }

        $host = mb_strtolower((string) $parts['host']);
        foreach (self::allowedHosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
