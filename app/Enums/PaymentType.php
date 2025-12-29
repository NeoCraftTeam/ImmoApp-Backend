<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentType: string
{
    case UNLOCK = 'unlock';
    case SUBSCRIPTION = 'subscription';
    case BOOST = 'boost';
}
