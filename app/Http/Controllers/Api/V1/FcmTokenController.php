<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Register / refresh FCM tokens for push notifications.
 */
final class FcmTokenController
{
    /**
     * POST /api/v1/fcm/token
     * Upsert a FCM token for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'    => ['required', 'string'],
            'platform' => ['required', 'string', 'in:web,android,ios'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        FcmToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id'      => $user->id,
                'platform'     => $validated['platform'],
                'last_used_at' => now(),
            ],
        );

        return response()->json(['message' => 'FCM token registered.']);
    }

    /**
     * DELETE /api/v1/fcm/token
     * Remove a FCM token (on logout or permission revocation).
     */
    public function destroy(Request $request): \Illuminate\Http\Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        FcmToken::where('token', $validated['token'])
            ->where('user_id', $request->user()?->id)
            ->delete();

        return response()->noContent();
    }
}
