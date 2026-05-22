<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Audit Item 11 — Tenant prescreening.
 *
 * Allows the landlord to define a list of prescreening questions on their ad.
 * Clients answer these questions when booking a viewing slot.
 */
final readonly class PrescreeningController
{
    /**
     * Update the prescreening questions for an ad.
     *
     * PATCH /api/v1/my/ads/{ad}/prescreening
     *
     * Accepts:
     *   questions: string[]  (max 10 questions, each max 500 chars)
     */
    public function update(Request $request, Ad $ad): JsonResponse
    {
        abort_unless(
            $ad->user_id === $request->user()->id,
            403,
            'Cette annonce ne vous appartient pas.'
        );

        $validated = $request->validate([
            'questions' => ['required', 'array', 'max:10'],
            'questions.*' => ['required', 'string', 'max:500'],
        ]);

        $ad->prescreening_questions = $validated['questions'];
        $ad->save();

        return ApiResponse::success(
            message: 'Questions de présélection mises à jour.',
            data: ['prescreening_questions' => $ad->prescreening_questions],
        );
    }

    /**
     * Clear all prescreening questions for an ad.
     *
     * DELETE /api/v1/my/ads/{ad}/prescreening
     */
    public function destroy(Request $request, Ad $ad): JsonResponse
    {
        abort_unless(
            $ad->user_id === $request->user()->id,
            403,
            'Cette annonce ne vous appartient pas.'
        );

        $ad->prescreening_questions = null;
        $ad->save();

        return ApiResponse::success(
            message: 'Questions de présélection supprimées.',
            data: ['prescreening_questions' => []],
        );
    }
}
