<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bulk operations on owned ads.
 *
 * Item 14: Bulk operations (bulk status update, bulk delete).
 */
final class BulkAdController
{
    /**
     * Bulk update the status of multiple owned ads.
     *
     * @param  Request  $request  { ids: string[], status: AdStatus }
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'uuid'],
            'status' => ['required', Rule::enum(AdStatus::class)],
        ]);

        $userId = auth()->id();
        $newStatus = AdStatus::from($validated['status']);

        // SEC: Non-admin owners can only transition to safe statuses.
        // AVAILABLE and DECLINED require admin approval to prevent bypassing moderation.
        $ownerAllowed = [AdStatus::DRAFT, AdStatus::PENDING, AdStatus::RENT, AdStatus::SOLD];
        if (!auth()->user()->isAdmin() && !in_array($newStatus, $ownerAllowed, true)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas mettre les annonces dans ce statut.',
            ], 403);
        }

        $ads = Ad::query()
            ->whereIn('id', $validated['ids'])
            ->where('user_id', $userId)
            ->get();

        $updated = 0;
        $failed = [];

        foreach ($ads as $ad) {
            try {
                $ad->transitionTo($newStatus);
                $updated++;
            } catch (\Throwable) {
                $failed[] = $ad->id;
            }
        }

        return response()->json([
            'message' => "{$updated} annonce(s) mise(s) à jour.",
            'updated' => $updated,
            'failed' => $failed,
        ]);
    }

    /**
     * Bulk soft-delete multiple owned ads.
     *
     * @param  Request  $request  { ids: string[] }
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'uuid'],
        ]);

        $userId = auth()->id();

        $deleted = Ad::query()
            ->whereIn('id', $validated['ids'])
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'message' => "{$deleted} annonce(s) supprimée(s).",
            'deleted' => $deleted,
        ]);
    }
}
