<?php

declare(strict_types=1);

namespace App\Support;

final class TourAssetToken
{
    /**
     * @return array{exp: int, sig: string}
     */
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

    /**
     * Sign all asset URLs inside a tour config so the image proxy accepts them.
     *
     * @param  array<string, mixed>  $tourConfig
     * @return array<string, mixed>
     */
    public static function signTourConfig(string $adId, array $tourConfig, int $ttlSeconds = 1800): array
    {
        if (!isset($tourConfig['scenes']) || !is_array($tourConfig['scenes'])) {
            return $tourConfig;
        }

        $token = self::issue($adId, $ttlSeconds);
        $exp = (int) $token['exp'];
        $sig = (string) $token['sig'];

        $tourConfig['scenes'] = collect($tourConfig['scenes'])
            ->map(function (array $scene) use ($adId, $exp, $sig): array {
                foreach (['image_url', 'tiles_base_url', 'fallback_base_url'] as $key) {
                    if (isset($scene[$key]) && is_string($scene[$key])) {
                        $scene[$key] = self::injectIntoProxyPath($scene[$key], $adId, $exp, $sig);
                    }
                }

                if (isset($scene['cube_map']) && is_array($scene['cube_map'])) {
                    $scene['cube_map'] = collect($scene['cube_map'])
                        ->map(fn (mixed $url): mixed => is_string($url)
                            ? self::injectIntoProxyPath($url, $adId, $exp, $sig)
                            : $url
                        )
                        ->values()
                        ->all();
                }

                return $scene;
            })
            ->values()
            ->all();

        return $tourConfig;
    }

    private static function signature(string $adId, int $exp): string
    {
        return hash_hmac('sha256', "{$adId}|{$exp}", (string) config('app.key'));
    }
}
