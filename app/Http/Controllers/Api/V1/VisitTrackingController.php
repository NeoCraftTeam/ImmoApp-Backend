<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\SiteVisit;
use App\Services\AcquisitionChannelClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class VisitTrackingController
{
    public function __construct(private readonly AcquisitionChannelClassifier $classifier) {}

    /**
     * Record an anonymous site visit. Designed to be best-effort:
     * - never returns 5xx (failures are logged + a 202 is returned),
     * - throttled per session_id to avoid hammering the DB on noisy clients
     *   (a single insert per (session_id, device_type, source, utm_*) combo
     *   per minute is enough for attribution analytics).
     */
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

        // Idempotency: dedupe identical sessions within 60 s to avoid duplicate
        // analytics rows when the SPA fires the tracker on every route change.
        $dedupKey = 'visit:'.hash('sha1', implode('|', [
            $validated['session_id'],
            $source,
            $deviceType,
            $referrerDomain ?? '',
            $validated['utm_source'] ?? '',
            $validated['utm_medium'] ?? '',
            $validated['utm_campaign'] ?? '',
        ]));

        if (Cache::add($dedupKey, 1, 60)) {
            try {
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
            } catch (Throwable $e) {
                Log::warning('SiteVisit ingestion failed', [
                    'error' => $e->getMessage(),
                    'session_id' => $validated['session_id'],
                ]);

                return response()->json(['status' => 'accepted', 'persisted' => false], 202);
            }
        }

        return response()->json(['status' => 'ok'], 201);
    }
}
