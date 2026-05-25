<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     *
     * @OA\Patch(
     *     path="/api/v1/ads/bulk/status",
     *     summary="Mise à jour de statut en masse",
     *     description="Change le statut de plusieurs annonces en une seule requête (max 50). Les propriétaires sont limités aux statuts safe (draft, pending, rent, sold). Les admins peuvent utiliser tous les statuts.",
     *     operationId="bulkUpdateAdStatus",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"ids","status"},
     *
     *         @OA\Property(property="ids", type="array", maxItems=50, @OA\Items(type="string", format="uuid")),
     *         @OA\Property(property="status", type="string", enum={"draft","pending","available","rent","sold","declined"})
     *     )),
     *
     *     @OA\Response(response=200, description="Résultat de l'opération", @OA\JsonContent(
     *
     *         @OA\Property(property="message", type="string", example="3 annonce(s) mise(s) à jour."),
     *         @OA\Property(property="updated", type="integer"),
     *         @OA\Property(property="failed", type="array", @OA\Items(type="string", format="uuid"))
     *     )),
     *
     *     @OA\Response(response=403, description="Statut non autorisé pour les propriétaires"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
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

        DB::transaction(function () use ($ads, $newStatus, &$updated, &$failed): void {
            foreach ($ads as $ad) {
                try {
                    $ad->transitionTo($newStatus);
                    $updated++;
                } catch (\Throwable) {
                    $failed[] = $ad->id;
                }
            }
        });

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
     *
     * @OA\Delete(
     *     path="/api/v1/ads/bulk",
     *     summary="Suppression en masse d'annonces",
     *     description="Supprime (soft delete) jusqu'à 50 annonces appartenant à l'utilisateur connecté en une seule requête.",
     *     operationId="bulkDeleteAds",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"ids"},
     *
     *         @OA\Property(property="ids", type="array", maxItems=50, @OA\Items(type="string", format="uuid"))
     *     )),
     *
     *     @OA\Response(response=200, description="Résultat de la suppression", @OA\JsonContent(
     *
     *         @OA\Property(property="message", type="string", example="5 annonce(s) supprimée(s)."),
     *         @OA\Property(property="deleted", type="integer")
     *     )),
     *
     *     @OA\Response(response=422, description="Données invalides")
     * )
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
