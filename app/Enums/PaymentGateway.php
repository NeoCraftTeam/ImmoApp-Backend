<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentGateway: string
{
    case GeniusPay = 'geniuspay';
    case Stripe = 'stripe';

    /**
     * Human-readable label (French) used in invoices, emails and admin UIs.
     */
    public function label(): string
    {
        return match ($this) {
            self::GeniusPay => 'GeniusPay',
            self::Stripe => 'Stripe',
        };
    }
}
