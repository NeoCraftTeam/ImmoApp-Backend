<?php

declare(strict_types=1);

namespace App\Enums;

enum CancelledBy: string
{
    case Client = 'client';
    case Landlord = 'landlord';
    case System = 'system';
}
