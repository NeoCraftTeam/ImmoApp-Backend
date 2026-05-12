<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageStatus: string
{
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
}
