<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case ORANGE_MONEY = 'orange_money';
    case MOBILE_MONEY = 'mobile_money';
    case CARD = 'card';

    /**
     * Human-readable label (French) for display in emails and invoices.
     */
    public function label(): string
    {
        return match ($this) {
            self::ORANGE_MONEY => 'Orange Money',
            self::MOBILE_MONEY => 'MTN Mobile Money',
            self::CARD => 'Carte bancaire',
        };
    }

    /**
     * Routing rule: which gateway processes this method.
     *
     * Mobile Money & Orange Money → Kpay (CEMAC/UEMOA mobile money rails, pawaPay-backed).
     * Carte bancaire → Stripe (PCI-compliant card processor, EUR billing).
     */
    public function gateway(): PaymentGateway
    {
        return match ($this) {
            self::ORANGE_MONEY,
            self::MOBILE_MONEY => PaymentGateway::Kpay,
            self::CARD => PaymentGateway::Stripe,
        };
    }
}
