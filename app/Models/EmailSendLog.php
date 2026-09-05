<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\EngagementMailGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A lifecycle email that was queued for a user, so the next run can hold back.
 *
 * Written by {@see EngagementMailGuard}, never by a mailable —
 * a mailable can be sent from anywhere, and a row here means "the engagement
 * scheduler decided to spend one of this user's slots".
 *
 * @property string $id
 * @property string $user_id
 * @property string $mail_key
 * @property Carbon $sent_at
 * @property-read User $user
 */
final class EmailSendLog extends Model
{
    use HasUuids;
    use MassPrunable;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'mail_key',
        'sent_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * The longest cap window is a week; a quarter of history is generous.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return self::where('sent_at', '<', now()->subDays(90));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
