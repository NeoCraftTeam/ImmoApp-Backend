<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EmailPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EmailPreferenceController
{
    /**
     * One-click unsubscribe via signed token (no auth required).
     * Disables the specified category for the user.
     */
    public function unsubscribe(Request $request, string $token): View
    {
        $preference = EmailPreference::where('unsubscribe_token', $token)->firstOrFail();
        $category = $request->query('category', 'all');

        if ($category === 'all') {
            $preference->update([
                'ad_updates' => false,
                'search_alerts' => false,
                'subscription_updates' => false,
                'survey_notifications' => false,
                'admin_notifications' => false,
                'welcome_emails' => false,
            ]);
        } elseif (in_array($category, [
            'ad_updates', 'search_alerts', 'subscription_updates',
            'survey_notifications', 'admin_notifications', 'welcome_emails',
        ], true)) {
            $preference->update([$category => false]);
        }

        return view('emails.unsubscribed', [
            'category' => $category,
            'user' => $preference->user,
        ]);
    }

    /**
     * RFC 8058 — one-click unsubscribe via POST (called by mail clients, e.g. Gmail).
     * Body contains `List-Unsubscribe=One-Click`. Returns 204 No Content.
     */
    public function unsubscribeOneClick(Request $request, string $token): Response
    {
        $preference = EmailPreference::where('unsubscribe_token', $token)->firstOrFail();
        $category = $request->query('category', 'all');

        if ($category === 'all') {
            $preference->update([
                'ad_updates' => false,
                'search_alerts' => false,
                'subscription_updates' => false,
                'survey_notifications' => false,
                'admin_notifications' => false,
                'welcome_emails' => false,
            ]);
        } elseif (in_array($category, [
            'ad_updates', 'search_alerts', 'subscription_updates',
            'survey_notifications', 'admin_notifications', 'welcome_emails',
        ], true)) {
            $preference->update([$category => false]);
        }

        return response()->noContent();
    }

    /**
     * Show the email preference management page.
     */
    public function manage(string $token): View
    {
        $preference = EmailPreference::where('unsubscribe_token', $token)->firstOrFail();

        return view('emails.preferences', [
            'preference' => $preference,
            'user' => $preference->user,
            'token' => $token,
        ]);
    }

    /**
     * Update email preferences from the management page.
     */
    public function update(Request $request, string $token): RedirectResponse
    {
        $preference = EmailPreference::where('unsubscribe_token', $token)->firstOrFail();

        $validated = $request->validate([
            'ad_updates' => 'boolean',
            'search_alerts' => 'boolean',
            'subscription_updates' => 'boolean',
            'survey_notifications' => 'boolean',
            'admin_notifications' => 'boolean',
            'welcome_emails' => 'boolean',
        ]);

        $preference->update($validated);

        return redirect()
            ->route('email.preferences', ['token' => $token])
            ->with('success', 'Vos préférences email ont été mises à jour.');
    }

    /**
     * API endpoint: Get current user's email preferences.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $preference = EmailPreference::getOrCreateForUser($user);

        return response()->json(['data' => $preference]);
    }

    /**
     * API endpoint: Update current user's email preferences.
     */
    public function apiUpdate(Request $request): JsonResponse
    {
        $user = $request->user();
        $preference = EmailPreference::getOrCreateForUser($user);

        $validated = $request->validate([
            'ad_updates' => 'sometimes|boolean',
            'search_alerts' => 'sometimes|boolean',
            'subscription_updates' => 'sometimes|boolean',
            'survey_notifications' => 'sometimes|boolean',
            'admin_notifications' => 'sometimes|boolean',
            'welcome_emails' => 'sometimes|boolean',
        ]);

        $preference->update($validated);

        return response()->json(['data' => $preference->fresh()]);
    }
}
