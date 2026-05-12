<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteVisit;

/**
 * Normalizes marketing traffic into a small set of segments for analytics
 * (site visits and registered users).
 */
final class AcquisitionChannelClassifier
{
    private const array SOCIAL_DOMAINS = [
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com',
        'linkedin.com', 'tiktok.com', 'youtube.com', 't.co', 'snapchat.com',
    ];

    private const array SEARCH_DOMAINS = [
        'google.com', 'google.fr', 'bing.com', 'yahoo.com',
        'duckduckgo.com', 'baidu.com', 'yandex.com',
    ];

    /**
     * Segment used on {@see SiteVisit::$source} and user acquisition rollups.
     */
    public function classifyFromReferrerAndUtm(
        ?string $referrerDomain,
        ?string $utmSource,
        ?string $utmMedium,
    ): string {
        $utmSource = $utmSource !== null && $utmSource !== '' ? mb_strtolower(trim($utmSource)) : null;
        $utmMedium = $utmMedium !== null && $utmMedium !== '' ? mb_strtolower(trim($utmMedium)) : null;

        if ($utmSource !== null || $utmMedium !== null) {
            return $this->classifyFromUtmMedium($utmMedium);
        }

        if ($referrerDomain === null || $referrerDomain === '') {
            return 'direct';
        }

        $referrerDomain = mb_strtolower($referrerDomain);

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

    /**
     * When UTM parameters are present, infer segment from medium (and paid search hints).
     */
    public function classifyFromUtmMedium(?string $utmMedium): string
    {
        if ($utmMedium === null || $utmMedium === '') {
            return 'referral';
        }

        $m = mb_strtolower(trim($utmMedium));

        return match (true) {
            in_array($m, ['cpc', 'ppc', 'paid', 'paidsearch', 'paid_search'], true) => 'paid',
            in_array($m, ['social', 'social-media', 'social_media'], true) => 'social',
            in_array($m, ['email', 'newsletter'], true) => 'email',
            default => 'referral',
        };
    }
}
