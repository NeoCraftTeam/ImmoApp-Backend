<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCookieConsentRequest;
use App\Models\CookieConsent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Records cookie consent so KeyHome can prove it to regulators (CNIL Art. 5-1-a / RGPD).
 *
 * The endpoint is intentionally unauthenticated — anonymous visitors must also
 * be able to log their consent. When the user is authenticated their UUID is
 * stored alongside the consent record for full traceability.
 */
final class CookieConsentController
{
    public function __invoke(StoreCookieConsentRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var User|null $user */
        $user = Auth::guard('sanctum')->user();

        CookieConsent::create([
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'policy_version' => $data['policy_version'] ?? 'v1',
            'analytics' => $data['analytics'],
            'marketing' => $data['marketing'],
            'consented_at' => now(),
        ]);

        return response()->json(['ok' => true], 201);
    }
}
