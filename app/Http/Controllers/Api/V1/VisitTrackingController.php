<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\SiteVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitTrackingController
{
    private const array SOCIAL_DOMAINS = [
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com',
        'linkedin.com', 'tiktok.com', 'youtube.com', 't.co',
    ];

    private const array SEARCH_DOMAINS = [
        'google.com', 'google.fr', 'bing.com', 'yahoo.com',
        'duckduckgo.com', 'baidu.com', 'yandex.com',
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:64',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
        ]);

        $referrer = $request->header('Referer', '');
        $referrerDomain = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;
        $source = $this->classifySource($referrerDomain, $validated['utm_source'] ?? null, $validated['utm_medium'] ?? null);

        $userAgent = strtolower($request->userAgent() ?? '');
        $deviceType = match (true) {
            str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android') => 'mobile',
            str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad') => 'tablet',
            default => 'desktop',
        };

        SiteVisit::create([
            'session_id' => $validated['session_id'],
            'source' => $source,
            'referrer_domain' => $referrerDomain ? mb_substr((string) $referrerDomain, 0, 255) : null,
            'utm_source' => $validated['utm_source'] ?? null,
            'utm_medium' => $validated['utm_medium'] ?? null,
            'utm_campaign' => $validated['utm_campaign'] ?? null,
            'user_id' => $request->user()?->id,
            'ip_hash' => hash('sha256', $request->ip() ?? 'unknown'),
            'device_type' => $deviceType,
            'visited_at' => now(),
        ]);

        return response()->json(['status' => 'ok'], 201);
    }

    private function classifySource(?string $referrerDomain, ?string $utmSource, ?string $utmMedium): string
    {
        if ($utmSource || $utmMedium) {
            return match (true) {
                in_array($utmMedium, ['cpc', 'ppc', 'paid'], true) => 'paid',
                in_array($utmMedium, ['social', 'social-media'], true) => 'social',
                in_array($utmMedium, ['email', 'newsletter'], true) => 'email',
                default => 'referral',
            };
        }

        if (!$referrerDomain) {
            return 'direct';
        }

        foreach (self::SOCIAL_DOMAINS as $domain) {
            if (str_contains($referrerDomain, $domain)) {
                return 'social';
            }
        }

        foreach (self::SEARCH_DOMAINS as $domain) {
            if (str_contains($referrerDomain, $domain)) {
                return 'organic';
            }
        }

        return 'referral';
    }
}
