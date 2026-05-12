<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PwaManifestController
{
    /** @var array<string, array{name: string, short_name: string, start_url: string, scope: string, theme_color: string, background_color: string, description: string, shortcut_name: string}> */
    private const array PANEL_CONFIG = [
        'admin' => [
            'name' => 'KeyHome Admin',
            'short_name' => 'KH Admin',
            'start_url' => '/',
            'scope' => '/',
            'theme_color' => '#F6475F',
            'background_color' => '#F6475F',
            'description' => 'Panneau d\'administration KeyHome. Gérez les annonces, utilisateurs et transactions.',
            'shortcut_name' => 'Tableau de bord',
        ],
        'owner' => [
            'name' => 'KeyHome Propriétaire',
            'short_name' => 'KH Owner',
            'start_url' => '/',
            'scope' => '/',
            'theme_color' => '#0D9488',
            'background_color' => '#0D9488',
            'description' => 'Espace propriétaire KeyHome. Gérez vos annonces et locations.',
            'shortcut_name' => 'Mon espace',
        ],
        'agency' => [
            'name' => 'KeyHome Agence',
            'short_name' => 'KH Agence',
            'start_url' => '/',
            'scope' => '/',
            'theme_color' => '#2563eb',
            'background_color' => '#2563eb',
            'description' => 'Espace agence KeyHome. Gérez vos mandats et clients.',
            'shortcut_name' => 'Mon agence',
        ],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $panel = $this->detectPanel($request);
        $config = self::PANEL_CONFIG[$panel];
        $baseUrl = $request->getSchemeAndHttpHost();

        $manifest = [
            'id' => $baseUrl.'/',
            'name' => $config['name'],
            'short_name' => $config['short_name'],
            'description' => $config['description'],
            'start_url' => $baseUrl.$config['start_url'],
            'scope' => $baseUrl.$config['scope'],
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => $config['background_color'],
            'theme_color' => $config['theme_color'],
            'lang' => 'fr',
            'dir' => 'ltr',
            'categories' => ['business', 'productivity'],
            'icons' => $this->icons($baseUrl),
            'screenshots' => [
                [
                    'src' => $baseUrl.'/pwa/screenshots/desktop.png',
                    'sizes' => '1280x720',
                    'type' => 'image/png',
                    'form_factor' => 'wide',
                    'label' => $config['name'].' — Vue bureau',
                ],
                [
                    'src' => $baseUrl.'/pwa/screenshots/mobile.png',
                    'sizes' => '750x1334',
                    'type' => 'image/png',
                    'form_factor' => 'narrow',
                    'label' => $config['name'].' — Vue mobile',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => $config['shortcut_name'],
                    'url' => $baseUrl.'/',
                    'icons' => [['src' => $baseUrl.'/pwa/icons/icon-96x96.png', 'sizes' => '96x96']],
                ],
            ],
            'prefer_related_applications' => false,
            'handle_links' => 'preferred',
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function detectPanel(Request $request): string
    {
        $host = $request->getHost();

        $adminDomain = config('filament.domains.admin_domain', '');
        $ownerDomain = config('filament.domains.owner_domain', '');
        $agencyDomain = config('filament.domains.agency_domain', '');

        if ($adminDomain && str_contains($host, explode('.', (string) $adminDomain)[0])) {
            return 'admin';
        }

        if ($ownerDomain && str_contains($host, explode('.', (string) $ownerDomain)[0])) {
            return 'owner';
        }

        if ($agencyDomain && str_contains($host, explode('.', (string) $agencyDomain)[0])) {
            return 'agency';
        }

        return 'admin';
    }

    /**
     * @return array<int, array{src: string, sizes: string, type: string, purpose: string}>
     */
    private function icons(string $baseUrl): array
    {
        $sizes = [72, 96, 128, 144, 152, 192, 384, 512];
        $icons = [];

        foreach ($sizes as $size) {
            $icons[] = [
                'src' => "{$baseUrl}/pwa/icons/icon-{$size}x{$size}.png",
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'any',
            ];
        }

        $icons[] = [
            'src' => "{$baseUrl}/pwa/icons/maskable-192x192.png",
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ];

        $icons[] = [
            'src' => "{$baseUrl}/pwa/icons/maskable-512x512.png",
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ];

        return $icons;
    }
}
