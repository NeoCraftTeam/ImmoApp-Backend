<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $subject
 * @property string $body
 * @property string|null $created_by
 * @property int $recipients_count
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class NewsletterCampaign extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'subject',
        'body',
        'created_by',
        'recipients_count',
        'sent_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'recipients_count' => 'integer',
        ];
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
