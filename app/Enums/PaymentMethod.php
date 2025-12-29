<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case ORANGE_MONEY = 'orange_money';
    case MOBILE_MONEY = 'mobile_money';
    case STRIPE = 'stripe';
    case FEDAPAY = 'fedapay';
}
