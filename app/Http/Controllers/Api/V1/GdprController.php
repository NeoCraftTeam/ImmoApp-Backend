<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Models\Payment;
use App\Models\PointTransaction;
use App\Models\Review;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="🔒 GDPR", description="GDPR data export and account deletion")
 */
final class GdprController
{
    /**
     * Export all personal data for the authenticated user (GDPR right of access).
     *
     * @OA\Get(
     *     path="/api/v1/my/data-export",
     *     operationId="exportUserData",
     *     tags={"🔒 GDPR"},
     *     summary="Exporter mes données personnelles",
     *     description="Retourne toutes les données personnelles de l'utilisateur connecté (GDPR droit d'accès).",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Données exportées avec succès"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function export(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $paymentsPayload = [];
        foreach ($user->payments()->get() as $payment) {
            if (!$payment instanceof Payment) {
                continue;
            }

            $paymentsPayload[] = [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'gateway' => $payment->gateway,
                'created_at' => $payment->created_at?->toIso8601String(),
            ];
        }

        $unlockedAdsPayload = [];
        foreach ($user->unlockedAds()->with('ad:id,title')->get() as $unlock) {
            if (!$unlock instanceof UnlockedAd) {
                continue;
            }

            $unlockedAdsPayload[] = [
                'ad_id' => $unlock->ad_id,
                'ad_title' => $unlock->ad?->title,
                'created_at' => $unlock->unlocked_at?->toIso8601String(),
            ];
        }

        $data = [
            'account' => [
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role->value,
                'type' => $user->type?->value,
                'city' => $user->city?->name,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at,
                'onboarding_completed_at' => $user->onboarding_completed_at?->toIso8601String(),
            ],
            'ads' => $user->ads()->with(['adType', 'quarter.city'])->get()->map(fn (Ad $ad): array => [
                'id' => $ad->id,
                'title' => $ad->title,
                'slug' => $ad->slug,
                'status' => $ad->status->value,
                'price' => $ad->price,
                'created_at' => $ad->created_at?->toIso8601String(),
            ])->toArray(),
            'reviews' => $user->reviews()->get()->map(fn (Review $review): array => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'ad_id' => $review->ad_id,
                'created_at' => $review->created_at?->toIso8601String(),
            ])->toArray(),
            'payments' => $paymentsPayload,
            'unlocked_ads' => $unlockedAdsPayload,
            'point_transactions' => $user->pointTransactions()->get()->map(fn (PointTransaction $pt): array => [
                'type' => $pt->type->value,
                'points' => $pt->points,
                'description' => $pt->description,
                'created_at' => $pt->created_at?->toIso8601String(),
            ])->toArray(),
            'email_preferences' => $user->emailPreference?->only([
                'ad_updates', 'search_alerts', 'subscription_updates',
                'survey_notifications', 'admin_notifications', 'welcome_emails',
                'engagement_emails', 'digest_emails',
            ]),
            'exported_at' => now()->toIso8601String(),
        ];

        return response()->json([
            'message' => 'Vos données personnelles ont été exportées.',
            'data' => $data,
        ]);
    }

    /**
     * Delete the authenticated user's account (GDPR right to erasure).
     *
     * @OA\Delete(
     *     path="/api/v1/my/account",
     *     operationId="deleteUserAccount",
     *     tags={"🔒 GDPR"},
     *     summary="Supprimer mon compte",
     *     description="Supprime le compte de l'utilisateur connecté (soft-delete). Les données seront anonymisées après 30 jours.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"confirmation"},
     *
     *             @OA\Property(property="confirmation", type="string", example="DELETE MY ACCOUNT")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Compte supprimé"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Confirmation manquante")
     * )
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'confirmation' => ['required', 'string', 'in:DELETE MY ACCOUNT,SUPPRIMER MON COMPTE'],
        ]);

        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();

            $user->ads()->delete();

            $user->delete();
        });

        return response()->json([
            'message' => 'Votre compte a été supprimé. Vos données seront anonymisées sous 30 jours.',
        ]);
    }
}
