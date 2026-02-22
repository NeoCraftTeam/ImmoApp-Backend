<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lightweight user-agent parser with no external dependencies.
 *
 * @phpstan-type ParsedAgent array{
 *     device_type: string,
 *     browser_name: string,
 *     operating_system: string,
 * }
 */
class UserAgentParser
{
    /**
     * Parse a User-Agent string and return device, browser, and OS information.
     *
     * @return ParsedAgent
     */
    public static function parse(?string $userAgent): array
    {
        if ($userAgent === null || $userAgent === '') {
            return [
                'device_type' => 'Inconnu',
                'browser_name' => 'Inconnu',
                'operating_system' => 'Inconnu',
            ];
        }

        return [
            'device_type' => self::detectDeviceType($userAgent),
            'browser_name' => self::detectBrowser($userAgent),
            'operating_system' => self::detectOs($userAgent),
        ];
    }

    private static function detectDeviceType(string $ua): string
    {
        if (stripos($ua, 'tablet') !== false || stripos($ua, 'ipad') !== false) {
            return 'Tablette';
        }

        if (preg_match('/mobile|android|iphone|ipod|blackberry|phone|windows phone/i', $ua)) {
            return 'Mobile';
        }

        return 'Ordinateur';
    }

    private static function detectBrowser(string $ua): string
    {
        $browsers = [
            'Edg' => 'Edge',
            'OPR' => 'Opera',
            'Opera' => 'Opera',
            'SamsungBrowser' => 'Samsung Internet',
            'UCBrowser' => 'UC Browser',
            'Firefox' => 'Firefox',
            'Chrome' => 'Chrome',
            'CriOS' => 'Chrome (iOS)',
            'FxiOS' => 'Firefox (iOS)',
            'Safari' => 'Safari',
            'MSIE' => 'Internet Explorer',
            'Trident' => 'Internet Explorer',
        ];

        foreach ($browsers as $key => $label) {
            if (str_contains($ua, $key)) {
                return $label;
            }
        }

        return 'Navigateur inconnu';
    }

    private static function detectOs(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows NT 11') => 'Windows 11',
            str_contains($ua, 'Windows NT 10') => 'Windows 10',
            str_contains($ua, 'Windows NT 6.3') => 'Windows 8.1',
            str_contains($ua, 'Windows NT 6.2') => 'Windows 8',
            str_contains($ua, 'Windows NT 6.1') => 'Windows 7',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'iPhone') => 'iOS',
            str_contains($ua, 'iPad') => 'iPadOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Linux') => 'Linux',
            str_contains($ua, 'CrOS') => 'Chrome OS',
            default => 'Système inconnu',
        };
    }
}
