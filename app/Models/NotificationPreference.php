<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationPreference extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'new_viewing_request',
        'viewing_confirmed',
        'new_review',
        'payment_received',
        'ad_expired',
        'lease_expiring',
        'new_message',
        'email_enabled',
        'push_enabled',
        'sms_enabled',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'new_viewing_request' => 'boolean',
            'viewing_confirmed' => 'boolean',
            'new_review' => 'boolean',
            'payment_received' => 'boolean',
            'ad_expired' => 'boolean',
            'lease_expiring' => 'boolean',
            'new_message' => 'boolean',
            'email_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
