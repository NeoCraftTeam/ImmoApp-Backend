<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Single emoji reaction left by a user on a chat message.
 *
 * Toggling: an emoji+user pair is unique on a given message; a second tap
 * on the same emoji removes the row instead of inserting a duplicate.
 *
 * @property string $id
 * @property string $message_id
 * @property string $user_id
 * @property string $emoji
 * @property Carbon $created_at
 * @property-read Message $message
 * @property-read User $user
 */
class MessageReaction extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'message_id',
        'user_id',
        'emoji',
    ];

    /** @return array<string, string> */
    #[\Override]
    public function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
