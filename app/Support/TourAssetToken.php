<?php

declare(strict_types=1);

namespace App\Support;

final class TourAssetToken
{
    public static function issue(string $adId, int $ttlSeconds = 1800): array
    {
        $exp = time() + max(60, $ttlSeconds);
        $sig = self::signature($adId, $exp);

        return ['exp' => $exp, 'sig' => $sig];
    }

    public static function validate(string $adId, ?string $exp, ?string $sig): bool
    {
        if (!is_string($exp) || !ctype_digit($exp) || !is_string($sig) || $sig === '') {
            return false;
        }

        $expiresAt = (int) $exp;
        if ($expiresAt < time()) {
            return false;
        }

        return hash_equals(self::signature($adId, $expiresAt), $sig);
    }

    public static function injectIntoProxyPath(string $url, string $adId, int $exp, string $sig): string
    {
        $needle = "/tour-image/{$adId}/";
        $replacement = "/tour-image/{$adId}/__t/{$exp}/{$sig}/";

        return str_replace($needle, $replacement, $url);
    }

    private static function signature(string $adId, int $exp): string
    {
        return hash_hmac('sha256', "{$adId}|{$exp}", (string) config('app.key'));
    }
}
