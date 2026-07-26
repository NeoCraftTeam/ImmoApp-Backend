<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Ai\AcquisitionChannelClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Resolves acquisition fields for a new user and links anonymous {@see SiteVisit} rows.
 */
final readonly class UtmAttributionService
{
    /** @var list<string> */
    public const array ATTRIBUTION_REQUEST_KEYS = [
        'session_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
    ];

    public function __construct(private AcquisitionChannelClassifier $classifier) {}

    /**
     * Build attributes to {@see User::forceFill()} before the first save.
     *
     * @param  array<string, mixed>  $payload  Validated input (session_id, utm_*, etc.)
     * @return array<string, string|null>
     */
    public function attributesForNewUser(Request $request, array $payload): array
    {
        $referrerHost = $this->referrerHostFromRequest($request);

        $utmSource = $this->nullableString($payload['utm_source'] ?? null, 100);
        $utmMedium = $this->nullableString($payload['utm_medium'] ?? null, 100);
        $utmCampaign = $this->nullableString($payload['utm_campaign'] ?? null, 255);
        $utmContent = $this->nullableString($payload['utm_content'] ?? null, 255);
        $utmTerm = $this->nullableString($payload['utm_term'] ?? null, 255);
        $sessionId = $this->nullableString($payload['session_id'] ?? null, 64);

        $hasDirectUtm = $utmSource !== null || $utmMedium !== null || $utmCampaign !== null
            || $utmContent !== null || $utmTerm !== null;

        if ($hasDirectUtm) {
            $acquisition = $this->classifier->classifyFromReferrerAndUtm($referrerHost, $utmSource, $utmMedium);

            return [
                'acquisition_source' => $acquisition,
                'utm_source' => $utmSource,
                'utm_medium' => $utmMedium,
                'utm_campaign' => $utmCampaign,
                'utm_content' => $utmContent,
                'utm_term' => $utmTerm,
                'referrer_domain' => $referrerHost,
            ];
        }

        if ($sessionId !== null) {
            $visit = SiteVisit::query()
                ->where('session_id', $sessionId)
                ->orderBy('visited_at')
                ->first();

            if ($visit !== null) {
                return [
                    'acquisition_source' => $visit->source ?? $this->classifier->classifyFromReferrerAndUtm(
                        $visit->referrer_domain,
                        $visit->utm_source,
                        $visit->utm_medium,
                    ),
                    'utm_source' => $visit->utm_source,
                    'utm_medium' => $visit->utm_medium,
                    'utm_campaign' => $visit->utm_campaign,
                    'utm_content' => $visit->utm_content,
                    'utm_term' => $visit->utm_term,
                    'referrer_domain' => $visit->referrer_domain ?? $referrerHost,
                ];
            }
        }

        if ($referrerHost !== null) {
            $acquisition = $this->classifier->classifyFromReferrerAndUtm($referrerHost, null, null);

            return [
                'acquisition_source' => $acquisition,
                'utm_source' => null,
                'utm_medium' => null,
                'utm_campaign' => null,
                'utm_content' => null,
                'utm_term' => null,
                'referrer_domain' => $referrerHost,
            ];
        }

        return [
            'acquisition_source' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_content' => null,
            'utm_term' => null,
            'referrer_domain' => null,
        ];
    }

    public function linkSessionVisitsToUser(User $user, ?string $sessionId): void
    {
        if ($sessionId === null || $sessionId === '') {
            return;
        }

        SiteVisit::query()
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);
    }

    /**
     * Fallback when the user was created without attribution (e.g. missing session on client).
     */
    public function tryAttributeFromRecentVisitByIp(User $user): void
    {
        if ($user->acquisition_source !== null) {
            return;
        }

        $ip = $user->registration_ip ?? $user->last_login_ip;
        if ($ip === null || $ip === '') {
            return;
        }

        $hash = hash('sha256', (string) $ip);

        $visit = SiteVisit::query()
            ->where('ip_hash', $hash)
            ->whereNull('user_id')
            ->where('visited_at', '>=', now()->subHours(72))
            ->orderByDesc('visited_at')
            ->first();

        if ($visit === null) {
            return;
        }

        $user->forceFill([
            'acquisition_source' => $visit->source ?? $this->classifier->classifyFromReferrerAndUtm(
                $visit->referrer_domain,
                $visit->utm_source,
                $visit->utm_medium,
            ),
            'utm_source' => $visit->utm_source,
            'utm_medium' => $visit->utm_medium,
            'utm_campaign' => $visit->utm_campaign,
            'utm_content' => $visit->utm_content,
            'utm_term' => $visit->utm_term,
            'referrer_domain' => $visit->referrer_domain,
        ])->saveQuietly();

        $this->linkSessionVisitsToUser($user, $visit->session_id);
    }

    private function referrerHostFromRequest(Request $request): ?string
    {
        $referrer = $request->header('Referer') ?? $request->header('Referrer') ?? '';
        if ($referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? Str::limit($host, 255, '') : null;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : Str::limit($trimmed, $max, '');
    }
}
