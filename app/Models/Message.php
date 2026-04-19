<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $sender_id
 * @property MessageType $type
 * @property string|null $body  (stored encrypted; access via decrypted_body)
 * @property string|null $body_iv
 * @property array<int, array<string, mixed>>|null $attachments
 * @property string|null $reply_to_id
 * @property MessageStatus $status
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Conversation|null $conversation
 * @property-read User|null $sender
 * @property-read Message|null $replyTo
 * @property-read string|null $decrypted_body
 */
class Message extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',
        'body',
        'body_iv',
        'attachments',
        'reply_to_id',
        'status',
        'read_at',
        'delivered_at',
        'edited_at',
    ];

    /** Never expose raw encrypted body or IV in API responses. */
    protected $hidden = ['body', 'body_iv'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'attachments'  => 'array',
            'read_at'      => 'datetime',
            'delivered_at' => 'datetime',
            'edited_at'    => 'datetime',
            'status'       => MessageStatus::class,
            'type'         => MessageType::class,
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    // ─── Accessors / Mutators ───────────────────────────────────────

    /**
     * Auto-decrypt message body on access.
     * Returns null for deleted messages or messages without encryption data.
     */
    public function getDecryptedBodyAttribute(): ?string
    {
        if ($this->body === null || $this->body_iv === null) {
            return null;
        }

        try {
            /** @var \App\Services\Chat\EncryptionService $enc */
            $enc = app(\App\Services\Chat\EncryptionService::class);

            return $enc->decrypt($this->body, $this->body_iv);
        } catch (\Throwable) {
            return null;
        }
    }
}
