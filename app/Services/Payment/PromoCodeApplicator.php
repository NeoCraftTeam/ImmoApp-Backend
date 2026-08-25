<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\DTOs\PromoCodeApplication;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;

/**
 * Applies promo codes to a payment amount and records their redemption.
 *
 * Extracted from PaymentController::initiate so the controller keeps only the
 * HTTP flow. Both methods are designed to run inside the initiate transaction:
 * apply() takes a FOR UPDATE lock so a single-use code cannot be redeemed twice
 * under concurrent requests, and recordUsage() persists the redemption once the
 * payment row exists.
 */
final readonly class PromoCodeApplicator
{
    /**
     * Resolve the promo code for this user + payment type and compute the
     * discounted amount. Returns the original amount with a null code when no
     * code is supplied or the code is not valid for the user.
     */
    public function apply(?string $code, User $user, string $type, float $amount): PromoCodeApplication
    {
        if (empty($code)) {
            return new PromoCodeApplication($amount, null);
        }

        $promoCode = PromoCode::where('code', strtoupper($code))
            ->lockForUpdate()
            ->first();

        if ($promoCode && $promoCode->isValidForUser($user, $type)) {
            return new PromoCodeApplication(
                max(0.0, $amount - $promoCode->calculateDiscount($amount)),
                $promoCode,
            );
        }

        return new PromoCodeApplication($amount, null);
    }

    /**
     * Persist the redemption and bump the code's usage counter.
     */
    public function recordUsage(PromoCode $promoCode, User $user, string $paymentId): void
    {
        PromoCodeUsage::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $user->id,
            'payment_id' => $paymentId,
        ]);
        $promoCode->increment('used_count');
    }
}
