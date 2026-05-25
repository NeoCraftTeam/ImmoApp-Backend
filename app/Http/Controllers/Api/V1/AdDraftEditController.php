<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Support\AdScoutSync;
use App\Support\ApiResponse;
use App\Support\GeoLocation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages a pending-edit draft payload for existing (non-DRAFT) ads.
 *
 * Flow:
 *   1. PATCH  /ads/{ad}/edit-draft           → save()    — store changes in draft_payload
 *   2. POST   /ads/{ad}/edit-draft/apply     → apply()   — promote draft_payload → live ad fields
 *   3. DELETE /ads/{ad}/edit-draft           → discard() — clear draft_payload without publishing
 *
 * @OA\Schema(
 *     schema="DraftPayload",
 *     type="object",
 *     description="Champs modifiables en attente de publication. Tous les champs sont optionnels.",
 *
 *     @OA\Property(property="title", type="string", example="Appartement F3 Bastos"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="price", type="number", example=150000),
 *     @OA\Property(property="price_period", type="string", enum={"mois","jour"}),
 *     @OA\Property(property="bedrooms", type="integer"),
 *     @OA\Property(property="bathrooms", type="integer"),
 *     @OA\Property(property="surface_area", type="number"),
 *     @OA\Property(property="has_parking", type="boolean"),
 *     @OA\Property(property="quarter_id", type="string", format="uuid"),
 *     @OA\Property(property="type_id", type="string", format="uuid"),
 *     @OA\Property(property="transaction_type", type="string", enum={"location","vente"}),
 *     @OA\Property(property="latitude", type="number"),
 *     @OA\Property(property="longitude", type="number"),
 *     @OA\Property(property="attributes", type="array", @OA\Items(type="string"))
 * )
 */
final class AdDraftEditController
{
    use AuthorizesRequests;

    private const array VALIDATION_RULES = [
        'title' => ['sometimes', 'nullable', 'string', 'max:255'],
        'description' => ['sometimes', 'nullable', 'string'],
        'adresse' => ['sometimes', 'nullable', 'string', 'max:500'],
        'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        'price_period' => ['sometimes', 'nullable', 'string', 'in:mois,jour'],
        'surface_area' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        'bedrooms' => ['sometimes', 'nullable', 'integer', 'min:0'],
        'bathrooms' => ['sometimes', 'nullable', 'integer', 'min:0'],
        'has_parking' => ['sometimes', 'nullable', 'boolean'],
        'deposit_amount' => ['sometimes', 'nullable', 'string', 'max:50'],
        'minimum_lease_duration' => ['sometimes', 'nullable', 'string', 'max:50'],
        'charges_forfaitaires' => ['sometimes', 'nullable', 'boolean'],
        'charges_montant_forfait' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        'charges_eau' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        'charges_electricite' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        'charges_autres' => ['sometimes', 'nullable', 'string', 'max:1000'],
        'quarter_id' => ['sometimes', 'nullable', 'uuid', 'exists:quarter,id'],
        'type_id' => ['sometimes', 'nullable', 'uuid', 'exists:ad_type,id'],
        'transaction_type' => ['sometimes', 'nullable', 'string', 'in:location,vente'],
        'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
        'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        'distance_main_road_m' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999'],
        'distance_shops_m' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999'],
        'distance_transport_m' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999'],
        'distance_school_m' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999'],
        'distance_hospital_m' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999'],
        'attributes' => ['sometimes', 'nullable', 'array', 'max:50'],
        'attributes.*' => ['string'],
    ];

    /**
     * Save (merge) incoming fields into draft_payload without touching the live ad.
     *
     * @OA\Patch(
     *     path="/api/v1/ads/{ad}/edit-draft",
     *     summary="Sauvegarder des modifications en brouillon",
     *     description="Fusionne les champs envoyés dans `draft_payload` sans toucher à l'annonce publiée. Peut être appelé plusieurs fois (merge cumulatif).",
     *     operationId="saveAdEditDraft",
     *     tags={"🔄 Brouillons"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, description="UUID de l'annonce", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DraftPayload")),
     *
     *     @OA\Response(response=200, description="Brouillon sauvegardé", @OA\JsonContent(
     *
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="draft_payload", ref="#/components/schemas/DraftPayload")
     *         )
     *     )),
     *
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="L'annonce est un brouillon — utilisez le flux standard")
     * )
     */
    public function save(Request $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);
        $this->requireNonDraftStatus($ad);

        $validated = $request->validate(self::VALIDATION_RULES);

        $existing = $ad->draft_payload ?? [];
        $merged = array_merge($existing, array_filter(
            $validated,
            static fn (mixed $v): bool => $v !== null
        ));

        // Keep attributes as-is (even empty array = explicit clear)
        if (array_key_exists('attributes', $validated)) {
            $merged['attributes'] = $validated['attributes'] ?? [];
        }

        $ad->draft_payload = $merged;
        $ad->saveQuietly();

        return ApiResponse::success('Modifications sauvegardées.', ['draft_payload' => $ad->draft_payload]);
    }

    /**
     * Promote draft_payload fields to the live ad record.
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/edit-draft/apply",
     *     summary="Appliquer les modifications brouillon",
     *     description="Promeut le `draft_payload` sur l'annonce publiée et vide le brouillon. L'annonce est re-validée avant application.",
     *     operationId="applyAdEditDraft",
     *     tags={"🔄 Brouillons"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Modifications appliquées"),
     *     @OA\Response(response=422, description="Aucune modification en attente ou données invalides"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function apply(Request $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);
        $this->requireNonDraftStatus($ad);

        $payload = $ad->draft_payload;

        if (empty($payload)) {
            return ApiResponse::error('Aucune modification en attente à appliquer.', 422, null);
        }

        // Re-validate the stored payload before applying to catch stale data
        $validated = validator($payload, self::VALIDATION_RULES)->validate();

        // Handle lat/lon → PostGIS point
        $point = GeoLocation::fromArray($validated)?->toPoint();
        unset($validated['latitude'], $validated['longitude']);
        if ($point !== null) {
            $validated['location'] = $point;
        }

        // Normalize attributes
        $attributes = $validated['attributes'] ?? null;
        unset($validated['attributes']);

        $toUpdate = array_filter($validated, static fn (mixed $v): bool => $v !== null);
        if (is_array($attributes)) {
            $toUpdate['attributes'] = array_values(
                array_unique(array_filter($attributes, is_string(...)))
            );
        }

        // Apply and clear the draft payload atomically
        $toUpdate['draft_payload'] = null;

        Ad::withoutSyncingToSearch(function () use ($ad, $toUpdate): void {
            $ad->forceFill($toUpdate);
            $ad->save();
        });

        AdScoutSync::syncSearchIndexBestEffort($ad);

        return ApiResponse::success('Modifications appliquées avec succès.', ['ad' => $ad->fresh()]);
    }

    /**
     * Discard draft_payload without modifying the live ad.
     *
     * @OA\Delete(
     *     path="/api/v1/ads/{ad}/edit-draft",
     *     summary="Annuler les modifications brouillon",
     *     description="Vide `draft_payload` sans toucher à l'annonce publiée.",
     *     operationId="discardAdEditDraft",
     *     tags={"🔄 Brouillons"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Brouillon annulé"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function discard(Request $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);
        $this->requireNonDraftStatus($ad);

        $ad->draft_payload = null;
        $ad->saveQuietly();

        return ApiResponse::success('Modifications annulées.');
    }

    private function requireNonDraftStatus(Ad $ad): void
    {
        if ($ad->status === AdStatus::DRAFT) {
            abort(422, 'Les brouillons utilisent le flux de sauvegarde standard.');
        }
    }
}
