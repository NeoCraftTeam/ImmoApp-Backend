<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case ORANGE_MONEY = 'orange_money';
    case MOBILE_MONEY = 'mobile_money';
    case CARD = 'card';
    case FLUTTERWAVE = 'flutterwave';

    /**
     * Human-readable label (French) for display in emails and invoices.
     */
    public function label(): string
    {
        return match ($this) {
            self::ORANGE_MONEY => 'Orange Money',
            self::MOBILE_MONEY => 'MTN Mobile Money',
            self::CARD => 'Carte bancaire',
            self::FLUTTERWAVE => 'Autre · Flutterwave',
        };
    }

    /**
     * Routing rule: which gateway processes this method.
     *
     * Mobile Money & Orange Money → Flutterwave (CEMAC/UEMOA mobile rails).
     * Carte bancaire → Stripe (PCI-compliant card processor, EUR billing).
     * `flutterwave` (legacy umbrella) stays on Flutterwave.
     */
    public function gateway(): PaymentGateway
    {
        return match ($this) {
            self::ORANGE_MONEY,
            self::MOBILE_MONEY,
            self::FLUTTERWAVE => PaymentGateway::Flutterwave,
            self::CARD => PaymentGateway::Stripe,
        };
    }
}
