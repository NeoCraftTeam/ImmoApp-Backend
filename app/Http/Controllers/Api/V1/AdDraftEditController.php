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
