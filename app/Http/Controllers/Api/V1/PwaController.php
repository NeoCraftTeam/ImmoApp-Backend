<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PwaController
{
    /**
     * Store a push notification subscription for the authenticated user.
     *
     * Uses the HasPushSubscriptions trait from the webpush package.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $subscription = $user->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
        );

        $subscription->update(['last_used_at' => now()]);

        return response()->json(['message' => 'Abonnement push enregistré.']);
    }

    /**
     * Remove a push notification subscription.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'url'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $user->deletePushSubscription($request->input('endpoint'));

        return response()->json(['message' => 'Abonnement push supprimé.']);
    }

    /**
     * Validate that the current session is still active.
     * Used by the PWA to enforce re-authentication on launch.
     * This endpoint uses the web middleware for session-based auth.
     */
    public function validateSession(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('web')->user();

        if (!$user) {
            return response()->json(['valid' => false], 401);
        }

        return response()->json([
            'valid' => true,
            'user' => $user->id,
        ]);
    }
}
