<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $ad_id
 * @property string $tenant_id
 * @property string $landlord_id
 * @property ConversationStatus $status
 * @property Carbon|null $tenant_last_read_at
 * @property Carbon|null $landlord_last_read_at
 * @property Carbon|null $last_message_at
 * @property string|null $e2ee_wrapped_key_tenant
 * @property string|null $e2ee_wrapped_key_landlord
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Ad|null $ad
 * @property-read User|null $tenant
 * @property-read User|null $landlord
 * @property-read Collection<int,Message> $messages
 * @property-read Message|null $latestMessage
 */
class Conversation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'ad_id',
        'tenant_id',
        'landlord_id',
        'status',
        'tenant_last_read_at',
        'landlord_last_read_at',
        'last_message_at',
        'last_message_preview',
        'last_message_id',
        'e2ee_wrapped_key_tenant',
        'e2ee_wrapped_key_landlord',
    ];

    protected $with = [];

    /** @return array<string, mixed> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'tenant_last_read_at' => 'datetime',
            'landlord_last_read_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** The most recently sent message, maintained by MessageService::send(). */
    public function latestMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────

    /** @param Builder<Conversation> $q */
    public function scopeForUser(Builder $q, string $userId): Builder
    {
        return $q->where(function (Builder $inner) use ($userId): void {
            $inner->where('tenant_id', $userId)
                ->orWhere('landlord_id', $userId);
        });
    }

    /** @param Builder<Conversation> $q */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', ConversationStatus::Active);
    }

    // ─── Accessors ─────────────────────────────────────────────────

    /**
     * Number of unread messages for the given user in this conversation.
     */
    public function unreadCountFor(User $user): int
    {
        $lastRead = $user->id === $this->tenant_id
            ? $this->tenant_last_read_at
            : $this->landlord_last_read_at;

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->when($lastRead, fn (Builder $q) => $q->where('created_at', '>', $lastRead))
            ->count();
    }

    /**
     * Return the other participant (not the given user).
     */
    public function otherParticipant(User $user): ?User
    {
        if ($user->id === $this->tenant_id) {
            return $this->landlord;
        }

        return $this->tenant;
    }
}
