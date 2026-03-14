<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $sender_id
 * @property string $body
 * @property bool $is_whatsapp_forwarded
 * @property \Carbon\Carbon|null $read_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class Message extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'is_whatsapp_forwarded',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_whatsapp_forwarded' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
