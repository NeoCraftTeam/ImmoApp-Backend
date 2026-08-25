<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\PromoCode;

/**
 * Outcome of PromoCodeApplicator::apply(): the amount after any discount and
 * the applied code (null when no valid code was supplied), so the caller can
 * record the usage once the payment row exists.
 */
final readonly class PromoCodeApplication
{
    public function __construct(
        public float $finalAmount,
        public ?PromoCode $promoCode,
    ) {}
}
