<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\TurnstileService;
use App\Support\ApiResponse;
use App\Support\FrontendRedirectGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exposes the Cloudflare Turnstile site key so the Next.js app can render the
 * widget without baking {@see NEXT_PUBLIC_TURNSTILE_SITE_KEY} at build time.
 * The site key is public by design; the secret stays server-side only.
 */
final class TurnstilePublicConfigController
{
    public function __invoke(Request $request): JsonResponse
    {
        $raw = config('services.turnstile.site_key');
        $siteKey = is_string($raw) && $raw !== '' ? $raw : null;

        /** @var TurnstileService $turnstile */
        $turnstile = app(TurnstileService::class);

        // Native mobile apps (`X-KeyHome-Client`) have no DOM / widget to run
        // Turnstile and the backend exempts them from verification on both
        // auth and payment flows. Report Turnstile as neither required nor to
        // be shown so a mobile client that ever consumes this endpoint never
        // renders a widget it cannot satisfy — mirrors the exemption applied
        // in LoginService / EnsuresCreditPurchasePassesTurnstile.
        $isMobileApp = FrontendRedirectGuard::isMobileAppRequest($request);

        return ApiResponse::success('OK', [
            'site_key' => $siteKey,
            'verification_required' => !$isMobileApp && $turnstile->isConfigured(),
            'show_credits_turnstile' => !$isMobileApp && $turnstile->shouldShowCreditsTurnstileStep(),
        ]);
    }
}
