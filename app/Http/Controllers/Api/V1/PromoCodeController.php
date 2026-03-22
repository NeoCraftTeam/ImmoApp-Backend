<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ApplyPromoCodeRequest;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class PromoCodeController
{
    public function validate(ApplyPromoCodeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $promoCode = PromoCode::where('code', strtoupper((string) $validated['code']))->first();

        if (!$promoCode || !$promoCode->isValidForUser($user, $validated['payment_type'])) {
            return response()->json([
                'message' => 'Code promo invalide, expiré ou déjà utilisé.',
                'valid' => false,
            ], 422);
        }

        $originalAmount = (float) $validated['original_amount'];
        $discountAmount = $promoCode->calculateDiscount($originalAmount);
        $finalAmount = max(0, $originalAmount - $discountAmount);

        return response()->json([
            'valid' => true,
            'code' => $promoCode->code,
            'description' => $promoCode->description,
            'discount_type' => $promoCode->discount_type,
            'discount_value' => $promoCode->discount_value,
            'discount_amount' => $discountAmount,
            'original_amount' => $originalAmount,
            'final_amount' => $finalAmount,
        ]);
    }
}
