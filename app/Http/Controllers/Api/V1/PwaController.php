<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="PWA", description="Push notifications et validation de session PWA")
 */
class PwaController
{
    /**
     * @OA\Post(
     *     path="/api/v1/pwa/push/subscribe",
     *     summary="Enregistrer un abonnement push",
     *     description="Enregistre ou met à jour l'abonnement aux notifications push pour l'utilisateur authentifié.",
     *     tags={"PWA"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"endpoint", "keys"},
     *
     *             @OA\Property(property="endpoint", type="string", format="url", description="URL d'abonnement push"),
     *             @OA\Property(property="keys", type="object",
     *                 @OA\Property(property="p256dh", type="string"),
     *                 @OA\Property(property="auth", type="string")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Abonnement enregistré",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Abonnement push enregistré."))
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $subscription = $user->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
        );

        $subscription->update(['last_used_at' => now()]);

        return response()->json(['message' => 'Abonnement push enregistré.']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/pwa/push/unsubscribe",
     *     summary="Supprimer un abonnement push",
     *     description="Supprime l'abonnement push pour l'endpoint fourni.",
     *     tags={"PWA"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"endpoint"},
     *
     *             @OA\Property(property="endpoint", type="string", format="url")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Abonnement supprimé",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Abonnement push supprimé."))
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'url'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $user->deletePushSubscription($request->input('endpoint'));

        return response()->json(['message' => 'Abonnement push supprimé.']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/pwa/session/validate",
     *     summary="Valider la session PWA",
     *     description="Vérifie que la session courante est toujours active. Utilisé par la PWA pour imposer une re-authentification au démarrage.",
     *     tags={"PWA"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Session valide",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="valid", type="boolean", example=true),
     *             @OA\Property(property="user", type="string", format="uuid", description="ID de l'utilisateur")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Session invalide ou expirée",
     *
     *         @OA\JsonContent(@OA\Property(property="valid", type="boolean", example=false))
     *     )
     * )
     */
    public function validateSession(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('web')->user();

        if (!$user) {
            return response()->json(['valid' => false], 401);
        }

        return response()->json([
            'valid' => true,
            'user' => $user->id,
        ]);
    }
}
