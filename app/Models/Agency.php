<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo
 * @property string $owner_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subscription> $subscriptions
 */
class Agency extends Model
{
    use hasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $table = 'agency';

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'owner_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'string',
    ];

    public function owner(): belongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    public function users(): hasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): ?\Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', \App\Enums\SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now())
            ->latest('ends_at');
    }

    /**
     * Check if agency has an active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', \App\Enums\SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now())
            ->exists();
    }

    /**
     * Get the current active subscription
     */
    public function getCurrentSubscription(): ?Subscription
    {
        /** @var Subscription|null $subscription */
        $subscription = $this->subscriptions()
            ->where('status', \App\Enums\SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();

        return $subscription;
    }
}
