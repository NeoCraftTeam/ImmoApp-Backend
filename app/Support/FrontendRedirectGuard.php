<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\Payment\PaymentNativeReturnController;

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
        // `exp` = Expo Go (utilisé pour tester les builds mobiles contre un
        // serveur distant, y compris preprod). On le garde autorisé partout :
        // c'est un custom scheme (handoff OS), non exploitable en open-redirect
        // web, et le callback est posé par le client authentifié lui-même.
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

    /**
     * True when the URI is a whitelisted mobile deep-link (custom app scheme).
     *
     * Hosted-checkout gateways require an http(s) success URL, so a deep-link
     * cannot be handed to them directly — it must first be wrapped in the
     * HTTPS return-bridge ({@see PaymentNativeReturnController})
     * which 302-redirects to it once the gateway comes back.
     */
    public static function isAllowedAppScheme(string $uri): bool
    {
        if ($uri === '' || strlen($uri) > 2048) {
            return false;
        }

        $scheme = parse_url($uri, PHP_URL_SCHEME);
        if (!is_string($scheme) || $scheme === '') {
            return false;
        }

        return in_array(mb_strtolower($scheme), self::allowedAppSchemes(), true);
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
