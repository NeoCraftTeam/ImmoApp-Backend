<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Ad;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        $ad->toggleVisibility();

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
    public function setStatus(Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        $validated = request()->validate([
            'status' => ['required', Rule::enum(AdStatus::class)],
        ]);

        try {
            $oldStatus = $ad->status;
            $newStatus = AdStatus::from($validated['status']);

            $ad->transitionTo($newStatus);

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour: '.$newStatus->getLabel(),
                'data' => [
                    'old_status' => $oldStatus->value,
                    'new_status' => $newStatus->value,
                ],
            ]);
        } catch (InvalidStatusTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
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
    public function publish(Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        return DB::transaction(function () use ($ad): JsonResponse {
            // Pessimistic lock to prevent concurrent publish attempts
            $ad = Ad::lockForUpdate()->find($ad->id);

            if ($ad->status !== AdStatus::DRAFT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les brouillons peuvent être publiés.',
                ], 422);
            }

            // Validate required fields are present before publishing
            $missing = [];
            if (!$ad->title) {
                $missing[] = 'titre';
            }
            if (!$ad->description) {
                $missing[] = 'description';
            }
            if (!$ad->adresse) {
                $missing[] = 'adresse';
            }
            if ($ad->getAttribute('price') === null) {
                $missing[] = 'prix';
            }
            if ($ad->getAttribute('surface_area') === null) {
                $missing[] = 'surface';
            }
            if (!$ad->quarter_id) {
                $missing[] = 'quartier';
            }
            if (!$ad->type_id) {
                $missing[] = 'type';
            }

            if (!empty($missing)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez compléter les champs obligatoires avant de publier : '.implode(', ', $missing),
                    'data' => ['missing_fields' => $missing],
                ], 422);
            }

            $ad->transitionTo(AdStatus::PENDING);

            activity()
                ->causedBy(auth()->user())
                ->performedOn($ad)
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
    public function setAvailability(Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        $validated = request()->validate([
            'available_from' => ['nullable', 'date'],
            'available_to' => ['nullable', 'date', 'after_or_equal:available_from'],
        ]);

        $ad->setAvailability(
            isset($validated['available_from']) ? new \DateTime($validated['available_from']) : null,
            isset($validated['available_to']) ? new \DateTime($validated['available_to']) : null
        );

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
