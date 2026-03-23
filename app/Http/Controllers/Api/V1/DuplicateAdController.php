<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;

/**
 * Duplicate an owned ad as a new DRAFT.
 */
final class DuplicateAdController
{
    /**
     * Create a copy of the given ad owned by the authenticated user.
     * The duplicate starts as DRAFT and gets a unique slug.
     */
    public function store(Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $duplicate = $ad->replicate([
            'slug',
            'is_boosted',
            'boost_score',
            'boost_expires_at',
            'boosted_at',
            'is_verified',
            'verified_at',
            'verification_status',
            'verification_notes',
            'verification_requested_at',
            'tour_published_at',
        ]);

        $duplicate->slug = Ad::generateUniqueSlug($ad->title.' - copie');
        $duplicate->title = $ad->title.' (copie)';
        $duplicate->forceFill(['status' => AdStatus::DRAFT]);
        $duplicate->save();

        return response()->json([
            'message' => 'Annonce dupliquée avec succès.',
            'data' => ['id' => $duplicate->id, 'slug' => $duplicate->slug],
        ], 201);
    }
}
