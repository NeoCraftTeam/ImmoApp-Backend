<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Granular notification preference management.
 */
final class NotificationPreferenceController
{
    public function show(): JsonResponse
    {
        $preferences = NotificationPreference::query()
            ->firstOrCreate(
                ['user_id' => auth()->id()],
                ['user_id' => auth()->id()]
            );

        return response()->json(['data' => $preferences]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'new_viewing_request' => ['sometimes', 'boolean'],
            'viewing_confirmed' => ['sometimes', 'boolean'],
            'new_review' => ['sometimes', 'boolean'],
            'payment_received' => ['sometimes', 'boolean'],
            'ad_expired' => ['sometimes', 'boolean'],
            'lease_expiring' => ['sometimes', 'boolean'],
            'new_message' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
        ]);

        $preferences = NotificationPreference::query()->updateOrCreate(
            ['user_id' => auth()->id()],
            array_merge($validated, ['user_id' => auth()->id()])
        );

        return response()->json(['data' => $preferences]);
    }
}
