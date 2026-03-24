<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\SiteVisit;
use App\Services\AcquisitionChannelClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VisitTrackingController
{
    public function __construct(private readonly AcquisitionChannelClassifier $classifier) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:64',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
        ]);

        $referrer = $request->header('Referer', '');
        $referrerDomain = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;
        $referrerDomain = is_string($referrerDomain) && $referrerDomain !== ''
            ? Str::limit($referrerDomain, 255, '')
            : null;
        $source = $this->classifier->classifyFromReferrerAndUtm(
            $referrerDomain,
            $validated['utm_source'] ?? null,
            $validated['utm_medium'] ?? null,
        );

        $userAgent = strtolower($request->userAgent() ?? '');
        $deviceType = match (true) {
            str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android') => 'mobile',
            str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad') => 'tablet',
            default => 'desktop',
        };

        SiteVisit::create([
            'session_id' => $validated['session_id'],
            'source' => $source,
            'referrer_domain' => $referrerDomain,
            'utm_source' => $validated['utm_source'] ?? null,
            'utm_medium' => $validated['utm_medium'] ?? null,
            'utm_campaign' => $validated['utm_campaign'] ?? null,
            'utm_content' => $validated['utm_content'] ?? null,
            'utm_term' => $validated['utm_term'] ?? null,
            'user_id' => $request->user()?->id,
            'ip_hash' => hash('sha256', $request->ip() ?? 'unknown'),
            'device_type' => $deviceType,
            'visited_at' => now(),
        ]);

        return response()->json(['status' => 'ok'], 201);
    }
}
