<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use App\Models\User;

/**
 * Builds canonical frontend URLs for ads, landlord profiles, and UTM-tagged links.
 *
 * Centralises all URL construction logic so that controllers, services, and
 * notifications share the same URL patterns. Extracted from QrCodeService
 * which was mixing URL building with SVG/PNG rendering concerns.
 */
final readonly class AdUrlBuilder
{
    /**
     * Build an absolute URL to the frontend for the given path.
     *
     * Reads `app.frontend_url` (falling back to `app.url`) and joins it with
     * the given path, ensuring exactly one slash at the join point.
     */
    public function absoluteFrontendUrl(string $path): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }

    /**
     * Build the canonical listing URL for an ad.
     *
     * Uses the ad's slug when available, falling back to its UUID.
     * UTM parameters are only appended when `$includeUtm` is `true` —
     * the default is clean (no tracking) to avoid polluting shared links.
     *
     * @param  array<string, string>  $extra  Additional query-string parameters to append.
     * @return string Fully-qualified URL, e.g. `https://keyhome.ci/ads/bel-appartement-abidjan`
     */
    public function adListingUrl(Ad $ad, string $utmMedium = 'qr', bool $includeUtm = false, array $extra = []): string
    {
        $slug = $ad->slug ?: $ad->id;
        $url = $this->absoluteFrontendUrl('/ads/'.$slug);

        if ($extra !== []) {
            $url = $this->appendUtm($url, $extra);
        }

        if (!$includeUtm) {
            return $url;
        }

        return $this->appendUtm($url, [
            'utm_source' => 'keyhome',
            'utm_medium' => $utmMedium,
            'utm_campaign' => 'owner_share',
            'utm_content' => 'ad_'.$ad->id,
        ]);
    }

    /**
     * Build the canonical landlord profile URL for a user.
     *
     * Uses the user's username when available, falling back to their UUID.
     * UTM parameters are only appended when `$includeUtm` is `true`.
     *
     * @param  array<string, string>  $extra  Additional query-string parameters to append.
     * @return string Fully-qualified URL, e.g. `https://keyhome.ci/bailleurs/jean-dupont`
     */
    public function landlordProfileUrl(User $user, string $utmMedium = 'qr', bool $includeUtm = false, array $extra = []): string
    {
        $username = $user->username ?: $user->id;
        $url = $this->absoluteFrontendUrl('/bailleurs/'.$username);

        if ($extra !== []) {
            $url = $this->appendUtm($url, $extra);
        }

        if (!$includeUtm) {
            return $url;
        }

        return $this->appendUtm($url, [
            'utm_source' => 'keyhome',
            'utm_medium' => $utmMedium,
            'utm_campaign' => 'owner_share',
            'utm_content' => 'profile_'.$user->id,
        ]);
    }

    /**
     * Append UTM (or any) query parameters to an existing URL.
     *
     * Correctly handles URLs that already contain a query string by using `&`
     * as the separator instead of `?`.
     *
     * @param  array<string, string>  $utmParams  Key-value pairs to append.
     * @return string The URL with parameters appended.
     */
    public function appendUtm(string $url, array $utmParams): string
    {
        if ($utmParams === []) {
            return $url;
        }

        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.http_build_query($utmParams);
    }
}
