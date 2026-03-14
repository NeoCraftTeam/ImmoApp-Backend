<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Services\KeyScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class KeyScoreController
{
    public function show(Ad $ad, KeyScoreService $service): JsonResponse
    {
        $cacheKey = 'keyscore_'.$ad->id.'_'.$ad->updated_at->timestamp;

        $result = Cache::remember($cacheKey, 3600, fn () => $service->compute($ad));

        return response()->json($result);
    }
}
