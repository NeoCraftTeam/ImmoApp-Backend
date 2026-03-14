<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $ad_id
 * @property string $tenant_id
 * @property string $landlord_id
 * @property \Carbon\Carbon|null $tenant_last_read_at
 * @property \Carbon\Carbon|null $landlord_last_read_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class Conversation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ad_id',
        'tenant_id',
        'landlord_id',
        'tenant_last_read_at',
        'landlord_last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_last_read_at' => 'datetime',
            'landlord_last_read_at' => 'datetime',
        ];
    }

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

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function unreadCountFor(User $user): int
    {
        $lastRead = $user->id === $this->tenant_id
            ? $this->tenant_last_read_at
            : $this->landlord_last_read_at;

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
            ->count();
    }
}
