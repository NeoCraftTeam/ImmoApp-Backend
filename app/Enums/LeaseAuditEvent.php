<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaseAuditEvent: string
{
    case Generated = 'generated';
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';
    case Signed = 'signed';
    case Sent = 'sent';
    case Countersigned = 'countersigned';
}
