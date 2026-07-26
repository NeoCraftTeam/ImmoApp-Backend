<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\PersonalAccessToken;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Active sessions (Sanctum personal access tokens) for the authenticated user.
 *
 * Lets a user see every device/session currently logged in to their account
 * and revoke any of them remotely. Revocation is a soft-revoke (`revoked_at`)
 * so it stays consistent with the token-family compromise detection in
 * {@see TokenService}; revoked tokens are rejected by
 * {@see PersonalAccessToken::findToken()}.
 *
 * Pure cookie/SPA sessions authenticate with a Sanctum TransientToken (no DB
 * row); for those `index()` simply returns an empty list.
 */
final class SessionController
{
    /**
     * List the authenticated user's active sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $this->currentTokenId($request);

        $sessions = $user->tokens()
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token): array => [
                'id' => $token->getKey(),
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'is_current' => $token->getKey() === $currentId,
            ])
            ->values();

        return response()->json(['data' => $sessions]);
    }

    /**
     * Revoke a single session. The current session cannot be revoked here
     * (use logout) to avoid a confusing self-disconnect.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->whereNull('revoked_at')->findOrFail($id);

        if ($token->getKey() === $this->currentTokenId($request)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas révoquer la session courante. Utilisez la déconnexion.',
            ], 422);
        }

        $token->forceFill(['revoked_at' => now()])->save();

        return response()->json(['message' => 'Session révoquée.']);
    }

    /**
     * Revoke every session except the current one ("log out other devices").
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $this->currentTokenId($request);

        $count = $user->tokens()
            ->whereNull('revoked_at')
            ->when($currentId !== null, fn ($query) => $query->where('id', '!=', $currentId))
            ->update(['revoked_at' => now()]);

        return response()->json([
            'message' => 'Autres sessions déconnectées.',
            'count' => $count,
        ]);
    }

    /**
     * The id of the token making this request, or null for transient
     * (cookie/SPA) sessions that have no persistent token row.
     */
    private function currentTokenId(Request $request): ?string
    {
        $token = $request->user()?->currentAccessToken();

        return $token instanceof SanctumPersonalAccessToken ? (string) $token->getKey() : null;
    }
}
