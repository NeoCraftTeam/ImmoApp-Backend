<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Inline PNG logos for transactional email so clients are not blocked by
 * Cloudflare / hotlink rules on {@code asset('images/...')} URLs.
 */
final class EmailBranding
{
    /** @var array<string, string|null> */
    private static array $cache = [];

    public static function pngBase64(string $relativePublicPath): ?string
    {
        if (array_key_exists($relativePublicPath, self::$cache)) {
            return self::$cache[$relativePublicPath];
        }

        $path = public_path($relativePublicPath);
        if (!is_readable($path)) {
            self::$cache[$relativePublicPath] = null;

            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            self::$cache[$relativePublicPath] = null;

            return null;
        }

        $b64 = base64_encode($raw);
        self::$cache[$relativePublicPath] = $b64;

        return $b64;
    }
}
