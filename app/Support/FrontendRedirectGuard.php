<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validates absolute URLs used as post-payment redirects (hosted-checkout callback_url).
 *
 * Two families are accepted:
 *  - Web (http/https): host must match {@see config('app.frontend_url')} and optional
 *    {@see config('app.oauth_allowed_redirect_hosts')} entries — same policy as OAuth redirects.
 *  - Mobile deep-links: the scheme must be one of {@see config('app.oauth_allowed_redirect_schemes')}
 *    (e.g. `keyhome://`, `keyhomeowners://`), or `exp://` for Expo Go during development.
 *    The scheme itself is the whitelist — only our app registers it on the device.
 */
final class FrontendRedirectGuard
{
    /**
     * App deep-link schemes allowed as post-payment redirects (mobile).
     *
     * @return list<string>
     */
    public static function allowedAppSchemes(): array
    {
        $schemes = ['exp'];
        $configured = (string) config('app.oauth_allowed_redirect_schemes', '');
        foreach (array_filter(array_map(trim(...), explode(',', $configured))) as $s) {
            $schemes[] = mb_strtolower($s);
        }

        /** @var list<string> */
        return array_values(array_unique($schemes));
    }

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
        if (!is_array($parts) || empty($parts['scheme'])) {
            return false;
        }

        $scheme = mb_strtolower($parts['scheme']);

        // Mobile deep-link : the app scheme is the whitelist. No host check —
        // `keyhome://credits/callback` has an authority-less path.
        if (in_array($scheme, self::allowedAppSchemes(), true)) {
            return true;
        }

        if (empty($parts['host'])) {
            return false;
        }

        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        if ($scheme === 'http' && !app()->environment('local', 'testing')) {
            return false;
        }

        $host = mb_strtolower($parts['host']);

        return array_any(self::allowedHosts(), fn ($allowed) => $host === $allowed || str_ends_with($host, '.'.$allowed));
    }
}
