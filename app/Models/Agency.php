<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo
 * @property string $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $owner
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Subscription> $subscriptions
 *
 * @OA\Schema(
 *     schema="Agency",
 *     type="object",
 *     title="Agency",
 *     description="Agence immobilière",
 *
 *     @OA\Property(property="id", type="string", format="uuid", description="ID unique de l'agence"),
 *     @OA\Property(property="name", type="string", description="Nom de l'agence"),
 *     @OA\Property(property="slug", type="string", description="Slug de l'agence"),
 *     @OA\Property(property="logo", type="string", nullable=true, description="URL du logo"),
 *     @OA\Property(property="owner_id", type="string", format="uuid", description="ID du propriétaire"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Date de création"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Date de mise à jour")
 * )
 */
class Agency extends Model
{
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): ?HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now())
            ->latest('ends_at');
    }

    /**
     * Check if agency has an active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now())
            ->exists();
    }

    /**
     * Get the current active subscription
     */
    public function getCurrentSubscription(): ?Subscription
    {
        /** @var Subscription|null */
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Agence « {$this->name} » {$eventName}");
    }
}
