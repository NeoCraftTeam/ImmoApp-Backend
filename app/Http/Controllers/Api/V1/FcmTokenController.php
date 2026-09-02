<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\DeleteFcmTokenRequest;
use App\Http\Requests\Api\V1\StoreFcmTokenRequest;
use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Register / refresh FCM tokens for push notifications.
 */
final class FcmTokenController
{
    /**
     * POST /api/v1/fcm/token
     * Upsert a FCM token for the authenticated user.
     */
    public function store(StoreFcmTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        FcmToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'],
                'last_used_at' => now(),
            ],
        );

        return response()->json(['message' => 'FCM token registered.']);
    }

    /**
     * DELETE /api/v1/fcm/token
     * Remove a FCM token (on logout or permission revocation).
     */
    public function destroy(DeleteFcmTokenRequest $request): Response
    {
        $validated = $request->validated();

        FcmToken::where('token', $validated['token'])
            ->where('user_id', $request->user()?->id)
            ->delete();

        return response()->noContent();
    }
}
