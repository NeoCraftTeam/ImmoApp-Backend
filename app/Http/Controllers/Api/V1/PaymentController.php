<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Requests\UnlockAdRequest;
use App\Models\Ad;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

class PaymentController
{
    /**
     * Unlock an ad for the authenticated user.
     *
     * @OA\Post(
     *     path="/api/v1/payments/unlock",
     *     summary="Unlock an ad",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ad_id", "payment_method"},
     *             @OA\Property(property="ad_id", type="string", format="uuid", example="019b42fb-e032-71ae-ae45-da62c36aab3f"),
     *             @OA\Property(property="payment_method", type="string", enum={"orange_money", "mobile_money", "stripe"}, example="orange_money")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ad unlocked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Ad unlocked successfully"),
     *             @OA\Property(property="payment_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=400, description="Ad already unlocked or invalid request"),
     *     @OA\Response(response=404, description="Ad not found")
     * )
     */
    public function unlockAd(UnlockAdRequest $request)
    {
        $user = $request->user();
        $ad = Ad::findOrFail($request->ad_id);

        if ($ad->isUnlockedFor($user)) {
            return response()->json(['message' => 'Ad already unlocked'], 400);
        }

        // -------------------------------------------------------------------------
        // ⚠️ TODO: PROD - INTÉGRER LA PASSERELLE DE PAIEMENT RÉELLE ICI ⚠️
        // -------------------------------------------------------------------------
        // Actuellement, le paiement est SIMULÉ et toujours accepté.
        // Avant la mise en prod, vous devez :
        // 1. Appeler l'API de paiement (Orange Money, MTN, Stripe...)
        // 2. Vérifier que la transaction est validée.
        // 3. Ne créer l'enregistrement Payment QUE si le paiement est confirmé.
        // -------------------------------------------------------------------------

        $payment = Payment::create([
            'user_id' => $user->id,
            'ad_id' => $ad->id,
            'amount' => 200, // This could be dynamic based on Ad price or global setting
            'status' => PaymentStatus::SUCCESS,
            'type' => PaymentType::UNLOCK,
            'payment_method' => PaymentMethod::from($request->payment_method),
            'transaction_id' => (string) Str::uuid(),
        ]);

        return response()->json([
            'message' => 'Ad unlocked successfully',
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id
        ]);
    }
}
