<?php

namespace App\Enums;

enum PaymentType: string
{
    case UNLOCK = 'unlock';
    case SUBSCRIPTION = 'subscription';
    case BOOST = 'boost';
}
