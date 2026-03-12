<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Facades\Filament;

final class PanelUrl
{
    public static function for(string $panelId, string $path = ''): string
    {
        $normalizedPath = ltrim($path, '/');

        try {
            $panel = Filament::getPanel($panelId, isStrict: false);
            $baseUrl = rtrim((string) $panel->getUrl(), '/');

            return $normalizedPath === ''
                ? $baseUrl
                : "{$baseUrl}/{$normalizedPath}";
        } catch (\Throwable) {
            // Fall back to config-based URL generation below.
        }

        $domain = match ($panelId) {
            'admin' => (string) config('filament.panels.admin_domain'),
            'bailleur' => (string) config('filament.panels.owner_domain'),
            'agency' => (string) config('filament.panels.agency_domain'),
            default => '',
        };

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        if ($domain !== '') {
            $baseUrl = "{$scheme}://{$domain}";

            return $normalizedPath === ''
                ? $baseUrl
                : "{$baseUrl}/{$normalizedPath}";
        }

        $panelPath = match ($panelId) {
            'admin' => 'admin',
            'bailleur' => 'owner',
            'agency' => 'agency',
            default => trim($panelId, '/'),
        };

        $baseUrl = rtrim((string) config('app.url'), '/')."/{$panelPath}";

        return $normalizedPath === ''
            ? $baseUrl
            : "{$baseUrl}/{$normalizedPath}";
    }
}
