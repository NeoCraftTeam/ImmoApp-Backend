<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\AiSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parses a property photo into structured search parameters via vision LLM.
 *
 * Tries GPT-4o-vision first, then Gemini Vision.
 * Returns the same JSON shape as NaturalSearchController so the frontend
 * can reuse the same navigation logic.
 *
 * POST /api/v1/ads/search/image
 * Content-Type: multipart/form-data
 * Body: image (jpeg|png|webp, max 5 MB)
 */
final readonly class AdImageSearchController
{
    public function __construct(
        private AiSearchService $aiSearchService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
        ]);

        $file = $request->file('image');
        $base64 = base64_encode((string) file_get_contents($file->getRealPath()));
        $mimeType = $file->getMimeType() ?? 'image/jpeg';

        $result = $this->aiSearchService->parseFromImage($base64, $mimeType);

        return response()->json($result);
    }
}
