<?php

declare(strict_types=1);

namespace App\Enums;

enum PointTransactionType: string
{
    case PURCHASE = 'purchase'; // bought a point package
    case UNLOCK = 'unlock';     // unlocked an ad contact (debit)
    case BONUS = 'bonus';       // welcome or promotional bonus
    case REFUND = 'refund';     // manual refund by admin
}
