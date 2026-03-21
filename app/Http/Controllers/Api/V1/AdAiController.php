<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\AiDescriptionEnhancer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdAiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'description' => ['required', 'string', 'max:10000'],
        ]);

        $enhanced = app(AiDescriptionEnhancer::class)->enhance($request->input('description'));

        return response()->json([
            'enhanced' => $enhanced,
        ]);
    }
}
