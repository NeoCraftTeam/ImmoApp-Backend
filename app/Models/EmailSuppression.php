<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * E-2 : Tracks email addresses that must never be emailed again.
 *
 * Populated automatically when Resend reports a hard bounce or spam complaint
 * via the webhook at POST /api/v1/webhooks/resend.
 *
 * @property int $id
 * @property string $email
 * @property string $reason 'bounce' | 'complaint' | 'unsubscribe'
 * @property string|null $resend_event_type
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class EmailSuppression extends Model
{
    protected $fillable = [
        'email',
        'reason',
        'resend_event_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
