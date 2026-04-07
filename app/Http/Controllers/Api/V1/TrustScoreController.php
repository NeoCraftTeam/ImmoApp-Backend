<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Resources\TrustScoreResource;
use App\Models\User;
use App\Services\TrustScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrustScoreController
{
    public function __construct(
        private readonly TrustScoreService $trustScoreService,
    ) {}

    public function show(User $user): JsonResponse
    {
        if ($user->trust_score_consent !== true) {
            return response()->json(['data' => null]);
        }

        $this->trustScoreService->getOrCompute($user);
        $roleContext = $this->resolveContext($user);
        $trustScore = $user->trustScores()->where('role_context', $roleContext)->first();

        return response()->json([
            'data' => $trustScore ? new TrustScoreResource($trustScore) : null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->trust_score_consent === null) {
            return response()->json([
                'consent_required' => true,
                'data' => null,
            ]);
        }

        if (!$user->trust_score_consent) {
            return response()->json([
                'consent_declined' => true,
                'data' => null,
            ]);
        }

        $this->trustScoreService->getOrCompute($user);
        $roleContext = $this->resolveContext($user);
        $trustScore = $user->trustScores()->where('role_context', $roleContext)->first();

        return response()->json([
            'data' => $trustScore ? new TrustScoreResource($trustScore) : null,
        ]);
    }

    public function consent(Request $request): JsonResponse
    {
        $request->validate(['consent' => 'required|boolean']);

        /** @var User $user */
        $user = $request->user();
        $user->update(['trust_score_consent' => $request->boolean('consent')]);

        if ($request->boolean('consent')) {
            $this->trustScoreService->compute($user);
        } else {
            $this->trustScoreService->invalidate($user);
        }

        return response()->json([
            'success' => true,
            'consent' => $user->trust_score_consent,
            'message' => $request->boolean('consent')
                ? 'Score de confiance activé.'
                : 'Score de confiance désactivé.',
        ]);
    }

    private function resolveContext(User $user): string
    {
        return $user->role === UserRole::AGENT ? 'landlord' : 'tenant';
    }
}
