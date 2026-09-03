<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UpdateNotificationPreferenceRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;

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

    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $preferences = NotificationPreference::query()->updateOrCreate(
            ['user_id' => auth()->id()],
            array_merge($validated, ['user_id' => auth()->id()])
        );

        return response()->json(['data' => $preferences]);
    }
}
