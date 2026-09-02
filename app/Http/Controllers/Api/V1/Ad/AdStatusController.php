<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ad;

use App\Enums\AdStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Requests\Api\V1\Ad\AutosaveAdRequest;
use App\Http\Requests\Api\V1\Ad\SetAdAvailabilityRequest;
use App\Http\Requests\Api\V1\Ad\SetAdStatusRequest;
use App\Http\Requests\Api\V1\PublishAdRequest;
use App\Models\Ad;
use App\Support\AdScoutSync;
use App\Support\GeoLocation;
use App\Support\SafeApiMessage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Handles ad visibility, status transitions, and availability dates.
 *
 * CRUD operations → AdController
 * Search & facets → AdSearchController
 * Geo proximity → AdGeoController
 */
final class AdStatusController
{
    use AuthorizesRequests;

    /**
     * Toggle ad visibility.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/toggle-visibility",
     *     summary="Toggle ad visibility",
     *     description="Hide or show an ad.",
     *     operationId="toggleAdVisibility",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Visibility toggled"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Ad not found")
     * )
     */
    public function toggleVisibility(Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        Ad::withoutSyncingToSearch(function () use ($ad): void {
            $ad->toggleVisibility();
        });
        AdScoutSync::syncSearchIndexBestEffort($ad->fresh());

        return response()->json([
            'success' => true,
            'message' => $ad->is_visible ? 'Annonce visible' : 'Annonce masquée',
            'data' => [
                'is_visible' => $ad->is_visible,
            ],
        ]);
    }

    /**
     * Set ad status.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/set-status",
     *     summary="Set ad status",
     *     description="Update the ad status to available, reserved, rent, or sold.",
     *     operationId="setAdStatus",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true,
     *
     *         @OA\JsonContent(required={"status"},
     *
     *             @OA\Property(property="status", type="string", enum={"available", "reserved", "rent", "sold"})
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Status updated"),
     *     @OA\Response(response=400, description="Invalid status transition"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Ad not found")
     * )
     */
    public function setStatus(SetAdStatusRequest $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        $validated = $request->validated();

        try {
            $oldStatus = $ad->status;
            $newStatus = AdStatus::from($validated['status']);

            Ad::withoutSyncingToSearch(function () use ($ad, $newStatus): void {
                $ad->transitionTo($newStatus);
            });
            AdScoutSync::syncSearchIndexBestEffort($ad->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour: '.$newStatus->getLabel(),
                'data' => [
                    'old_status' => $oldStatus->value,
                    'new_status' => $newStatus->value,
                ],
            ]);
        } catch (InvalidStatusTransitionException $e) {
            $payload = SafeApiMessage::envelope($e->getMessage(), 'INVALID_STATUS_TRANSITION', 400);

            return response()->json([
                'success' => false,
                ...$payload,
            ], 400);
        }
    }

    /**
     * Publish a draft ad (transitions DRAFT → PENDING for admin review).
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/publish",
     *     summary="Publish a draft ad",
     *     description="Transition a draft ad to pending status for admin review. Validates that all required fields are filled.",
     *     operationId="publishDraftAd",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Draft published"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Ad not found"),
     *     @OA\Response(response=422, description="Missing required fields or not a draft")
     * )
     */
    public function publish(PublishAdRequest $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        // PublishAdRequest::withValidator() has already confirmed the ad is a
        // DRAFT with all required fields filled. Inside the transaction we re-lock
        // the row to guard against concurrent publishes, but we don't repeat the
        // field-presence checks — the FormRequest owns that responsibility.
        $response = DB::transaction(function () use ($ad): JsonResponse {
            $locked = Ad::lockForUpdate()->find($ad->id);

            if (!$locked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Annonce introuvable.',
                ], 404);
            }

            // Guard against a concurrent status change between FormRequest
            // validation and the lock being acquired.
            if ($locked->status !== AdStatus::DRAFT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les brouillons peuvent être publiés.',
                ], 422);
            }

            Ad::withoutSyncingToSearch(function () use ($locked): void {
                $locked->transitionTo(AdStatus::PENDING);
            });

            activity()
                ->causedBy(auth()->user())
                ->performedOn($locked)
                ->withProperties(['old_status' => 'draft', 'new_status' => 'pending'])
                ->log('Ad published from draft');

            return response()->json([
                'success' => true,
                'message' => 'Annonce soumise pour validation.',
                'data' => [
                    'old_status' => 'draft',
                    'new_status' => 'pending',
                ],
            ]);
        });

        if ($response->isSuccessful()) {
            AdScoutSync::syncSearchIndexBestEffort($ad->fresh());
        }

        return $response;
    }

    /**
     * Lightweight JSON-only autosave for text fields of a draft ad.
     *
     * @OA\Patch(
     *     path="/api/v1/ads/{ad}/autosave",
     *     summary="Autosave draft ad text fields",
     *     description="Partially updates a draft ad with any subset of allowed text fields. No image processing, no status transition.",
     *     operationId="autosaveDraftAd",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string", nullable=true),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="price", type="number", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Autosaved"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Ad not found"),
     *     @OA\Response(response=422, description="Not a draft or validation error")
     * )
     */
    public function autosave(AutosaveAdRequest $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        if ($ad->status !== AdStatus::DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les brouillons peuvent être sauvegardés automatiquement.',
            ], 422);
        }

        $validated = $request->validated();

        // Latitude/longitude are positioned as a single PostGIS point; let
        // GeoLocation drop them when both are missing so we don't overwrite
        // a previously-saved coordinate with NULL on a partial autosave.
        $point = GeoLocation::fromArray($validated)?->toPoint();
        unset($validated['latitude'], $validated['longitude']);
        if ($point !== null) {
            $validated['location'] = $point;
        }

        // Empty attribute array is a valid intent: the user just deselected
        // every chip — keep [] in $toUpdate so the column is cleared.
        $attributes = $validated['attributes'] ?? null;
        unset($validated['attributes']);

        /** @var array<string, mixed> $toUpdate */
        $toUpdate = array_filter(
            $validated,
            static fn (mixed $v): bool => $v !== null
        );

        if (is_array($attributes)) {
            $toUpdate['attributes'] = array_values(
                array_unique(array_filter($attributes, is_string(...)))
            );
        }

        if (!empty($toUpdate)) {
            Ad::withoutSyncingToSearch(function () use ($ad, $toUpdate): void {
                $ad->forceFill($toUpdate);
                $ad->save();
            });
            AdScoutSync::syncSearchIndexBestEffort($ad);
        }

        return response()->json([
            'success' => true,
            'message' => 'Brouillon sauvegardé automatiquement.',
            'data' => [
                'updated_at' => $ad->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Set ad availability dates.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/set-availability",
     *     summary="Set ad availability dates",
     *     description="Set when the ad/property is available.",
     *     operationId="setAdAvailability",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="available_from", type="string", format="date", nullable=true),
     *             @OA\Property(property="available_to", type="string", format="date", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Availability updated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Ad not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function setAvailability(SetAdAvailabilityRequest $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        $validated = $request->validated();

        Ad::withoutSyncingToSearch(function () use ($ad, $validated): void {
            $ad->setAvailability(
                isset($validated['available_from']) ? new \DateTime($validated['available_from']) : null,
                isset($validated['available_to']) ? new \DateTime($validated['available_to']) : null
            );
        });
        AdScoutSync::syncSearchIndexBestEffort($ad->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Disponibilité mise à jour',
            'data' => [
                'available_from' => $ad->available_from?->format('Y-m-d'),
                'available_to' => $ad->available_to?->format('Y-m-d'),
                'is_currently_available' => $ad->isCurrentlyAvailable(),
            ],
        ]);
    }
}
