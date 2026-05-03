<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Exposes the Cloudflare Turnstile site key so the Next.js app can render the
 * widget without baking {@see NEXT_PUBLIC_TURNSTILE_SITE_KEY} at build time.
 * The site key is public by design; the secret stays server-side only.
 */
final class TurnstilePublicConfigController
{
    public function __invoke(): JsonResponse
    {
        $raw = config('services.turnstile.site_key');
        $siteKey = is_string($raw) && $raw !== '' ? $raw : null;

        return ApiResponse::success('OK', [
            'site_key' => $siteKey,
        ]);
    }
}
