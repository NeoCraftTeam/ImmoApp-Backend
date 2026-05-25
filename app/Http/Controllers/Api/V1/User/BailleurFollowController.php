<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Toggle follow / unfollow a bailleur (agent user) by username.
 */
final class BailleurFollowController
{
    /**
     * @OA\Post(
     *     path="/api/v1/bailleurs/{username}/follow",
     *     tags={"👤 Bailleurs"},
     *     summary="Mettre en favoris / retirer des favoris un bailleur",
     *     operationId="toggleFollowBailleur",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="username", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="État du suivi mis à jour"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Impossible de se suivre soi-même"),
     *     @OA\Response(response=404, description="Bailleur introuvable")
     * )
     */
    public function toggle(Request $request, string $username): JsonResponse
    {
        /** @var User $follower */
        $follower = $request->user();

        $bailleur = User::query()
            ->where('username', $username)
            ->firstOrFail();

        if ($follower->id === $bailleur->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous suivre vous-même.'], 403);
        }

        $isFollowing = DB::table('user_follows')
            ->where('follower_id', $follower->id)
            ->where('followed_id', $bailleur->id)
            ->exists();

        if ($isFollowing) {
            DB::table('user_follows')
                ->where('follower_id', $follower->id)
                ->where('followed_id', $bailleur->id)
                ->delete();
            $following = false;
        } else {
            DB::table('user_follows')->insertOrIgnore([
                'follower_id' => $follower->id,
                'followed_id' => $bailleur->id,
                'created_at' => now(),
            ]);
            $following = true;
        }

        return response()->json([
            'following' => $following,
            'followers_count' => $bailleur->followers()->count(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/bailleurs/{username}/follow",
     *     tags={"👤 Bailleurs"},
     *     summary="Vérifier si l'utilisateur suit un bailleur",
     *     operationId="checkFollowBailleur",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="username", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="État du suivi"),
     *     @OA\Response(response=404, description="Bailleur introuvable")
     * )
     */
    public function status(Request $request, string $username): JsonResponse
    {
        $bailleur = User::query()
            ->where('username', $username)
            ->firstOrFail();

        $user = $request->user();

        $isFollowing = $user !== null && DB::table('user_follows')
            ->where('follower_id', $user->id)
            ->where('followed_id', $bailleur->id)
            ->exists();

        return response()->json([
            'following' => $isFollowing,
            'followers_count' => $bailleur->followers()->count(),
        ]);
    }
}
