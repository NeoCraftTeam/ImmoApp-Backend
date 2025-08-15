<?php

namespace App;

enum PaymentMethod: string
{
    case ORANGE_MONEY = 'orange_money';
    case MOBILE_MONEY = 'mobile_money';
    case STRIPE = 'stripe';
}
