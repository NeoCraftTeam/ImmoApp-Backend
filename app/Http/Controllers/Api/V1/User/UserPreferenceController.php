<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\UserRole;
use App\Mail\OwnerProfileCompletedMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

final class UserPreferenceController
{
    /**
     * Mark the authenticated user's onboarding as completed.
     */
    public function completeOnboarding(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->onboarding_completed_at) {
            $user->onboarding_completed_at = now();
            $user->save();

            if ($user->role === UserRole::AGENT && $user->email && !str_ends_with($user->email, '@clerk.local')) {
                Mail::to($user->email, $user->firstname)->queue(new OwnerProfileCompletedMail($user));
            }
        }

        return response()->json(['message' => 'Onboarding complété.']);
    }

    /**
     * Track home page visit for greeting.
     */
    public function trackHomeVisit(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->last_home_visit_at = now();
        $user->save();

        return response()->json(['last_home_visit_at' => now()->toIso8601String()]);
    }

    /**
     * Update user preferences (e.g. survey_postponed_ids).
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'survey_postponed_ids' => 'sometimes|array',
            'survey_postponed_ids.*' => 'string',
        ]);

        /** @var User $user */
        $user = $request->user();
        $prefs = $user->preferences ?? [];

        if (array_key_exists('survey_postponed_ids', $validated)) {
            $prefs['survey_postponed_ids'] = array_values(array_unique($validated['survey_postponed_ids']));
        }

        $user->preferences = $prefs;
        $user->save();

        return response()->json(['preferences' => $user->preferences]);
    }

    /**
     * Change user's preferred language/locale.
     */
    public function updateLocale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:fr,en'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->locale = $validated['locale'];
        $user->save();

        return response()->json(['locale' => $user->locale]);
    }
}
