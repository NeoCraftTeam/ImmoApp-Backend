<?php

namespace App;

enum PaymentType: string
{
    case UNLOCK = 'unlock';
    case SUBSCRIPTION = 'subscription';
    case BOOST = 'boost';
}
