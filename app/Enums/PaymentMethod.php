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
            self::MOBILE_MONEY => 'Mobile Money',
            self::CARD => 'Carte bancaire',
            self::FLUTTERWAVE => 'Flutterwave',
        };
    }
}
