<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdStatus;
use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Concerns\HasPropertyAttributes;
use App\Models\Concerns\HasVisibility;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\AdFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * @property-read Quarter|null $quarter
 * @property-read User|null $user
 * @property-read Agency|null $agency
 * @property-read AdType|null $ad_type
 *
 * @method static AdFactory factory($count = null, $state = [])
 * @method static Builder<static>|Ad newModelQuery()
 * @method static Builder<static>|Ad newQuery()
 * @method static Builder<static>|Ad onlyTrashed()
 * @method static Builder<static>|Ad query()
 * @method static Builder<static>|Ad withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Ad withoutTrashed()
 *
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property string $adresse
 * @property string|null $price
 * @property string $surface_area
 * @property int $bedrooms
 * @property int $bathrooms
 * @property bool $has_parking
 * @property Point|null $location
 * @property AdStatus $status
 * @property string|null $expires_at
 * @property string $user_id
 * @property string $quarter_id
 * @property string $type_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder<static>|Ad whereAdresse($value)
 * @method static Builder<static>|Ad whereBathrooms($value)
 * @method static Builder<static>|Ad whereBedrooms($value)
 * @method static Builder<static>|Ad whereCreatedAt($value)
 * @method static Builder<static>|Ad whereDeletedAt($value)
 * @method static Builder<static>|Ad whereDescription($value)
 * @method static Builder<static>|Ad whereExpiresAt($value)
 * @method static Builder<static>|Ad whereHasParking($value)
 * @method static Builder<static>|Ad whereId($value)
 * @method static Builder<static>|Ad whereLocation($value)
 * @method static Builder<static>|Ad wherePrice($value)
 * @method static Builder<static>|Ad whereQuarterId($value)
 * @method static Builder<static>|Ad whereSlug($value)
 * @method static Builder<static>|Ad whereStatus($value)
 * @method static Builder<static>|Ad whereSurfaceArea($value)
 * @method static Builder<static>|Ad whereTitle($value)
 * @method static Builder<static>|Ad whereTypeId($value)
 * @method static Builder<static>|Ad whereUpdatedAt($value)
 * @method static Builder<static>|Ad whereUserId($value)
 *
 * @mixin Eloquent
 */
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Zap\Models\Concerns\HasSchedules;

class Ad extends Model implements HasMedia
{
    use HasFactory, HasSchedules, HasUuids, LogsActivity, SoftDeletes;
    use HasPropertyAttributes, HasVisibility, InteractsWithMedia, Searchable;

    /**
     * Statuses that are visible on the public frontend.
     *
     * @var array<int, AdStatus>
     */
    public const array PUBLIC_STATUSES = [
        AdStatus::AVAILABLE,
        AdStatus::RESERVED,
    ];

    protected $table = 'ad';

    /**
     * SEC-006: status excluded — use transitionTo() or forceFill() only.
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'adresse',
        'price',
        'surface_area',
        'bedrooms',
        'bathrooms',
        'has_parking',
        'location',
        'is_visible',
        'available_from',
        'available_to',
        'attributes',
        'expires_at',
        'quarter_id',
        'type_id',
        'agency_id',
        'deposit_amount',
        'minimum_lease_duration',
        'detailed_charges',
        'charges_forfaitaires',
        'charges_montant_forfait',
        'charges_eau',
        'charges_electricite',
        'charges_autres',
        'has_3d_tour',
        'tour_config',
        'tour_published_at',
        'is_verified',
        'verified_at',
        'verification_status',
        'verification_notes',
        'verification_requested_at',
    ];

    protected $hidden = [
        'location',
        'created_at',
        'updated_at',
        'deleted_at',
        'agency_id',
    ];

    /** @var list<string> */
    protected $appends = ['tour_scenes_count'];

    protected $casts = [
        'location' => Point::class, // Assuming 'point' is a custom cast for PostGIS
        'status' => AdStatus::class,
        'has_parking' => 'boolean',
        'is_visible' => 'boolean',
        'available_from' => 'date',
        'available_to' => 'date',
        'attributes' => 'array',
        'expires_at' => 'datetime',
        'price' => 'decimal:2',
        'is_boosted' => 'boolean',
        'boost_expires_at' => 'datetime',
        'boosted_at' => 'datetime',
        'charges_forfaitaires' => 'boolean',
        'charges_montant_forfait' => 'integer',
        'charges_eau' => 'integer',
        'charges_electricite' => 'integer',
        'has_3d_tour' => 'boolean',
        'tour_config' => 'array',
        'tour_published_at' => 'datetime',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'verification_status' => VerificationStatus::class,
        'verification_requested_at' => 'datetime',
    ];

    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($ad): void {
            if (empty($ad->user_id)) {
                $ad->user_id = auth()->id();
            }

            if (empty($ad->slug)) {
                $ad->slug = self::generateUniqueSlug($ad->title);
            }

            // Automatiquement lier l'agence de l'utilisateur créateur
            if (empty($ad->agency_id)) {
                $ad->agency_id = auth()->user()?->agency_id;
            }

            // Default status when not set (status is not fillable for security)
            if (empty($ad->status)) {
                $ad->status = AdStatus::PENDING;
            }
        });

        static::updating(function ($ad): void {
            if ($ad->isDirty('title')) {
                $ad->slug = self::generateUniqueSlug($ad->title, $ad->id);
            }
        });
    }

    /**
     * Transition the ad to a new status, validating the transition.
     *
     * @throws InvalidStatusTransitionException
     */
    public function transitionTo(AdStatus $newStatus): void
    {
        if ($this->status === $newStatus) {
            return; // No-op if already in the target state
        }

        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidStatusTransitionException($this->status, $newStatus);
        }

        $this->status = $newStatus;
        $this->save();
    }

    /** Return the number of 360\u00b0 scenes in the tour config. */
    public function getTourScenesCountAttribute(): int
    {
        if (!$this->tour_config) {
            return 0;
        }

        return count($this->tour_config['scenes'] ?? []);
    }

    /**
     * Scope \u2014 ads that have a published 3D tour.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withTour(Builder $query): Builder
    {
        return $query->where('has_3d_tour', true)->whereNotNull('tour_config');
    }

    public static function generateUniqueSlug(string $title, ?string $ignoreId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $i = 1;
        while (
            self::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $original.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'adresse' => $this->adresse,
            'price' => (float) $this->price,
            'surface_area' => (float) $this->surface_area,
            'bedrooms' => (int) $this->bedrooms,
            'bathrooms' => (int) $this->bathrooms,
            'has_parking' => (bool) $this->has_parking,
            'status' => $this->status,
            'is_visible' => (bool) $this->is_visible,

            // Relations — vérifier qu'elles existent
            'city' => $this->quarter?->city?->name,
            'quarter' => $this->quarter?->name,
            'type' => $this->ad_type?->name,
            'type_id' => $this->type_id,
            'quarter_id' => $this->quarter_id,

            // Pour la recherche géographique (optionnel)
            '_geo' => $this->location ? [
                'lat' => $this->location->getY(),
                'lng' => $this->location->getX(),
            ] : null,

            'created_at' => $this->created_at?->timestamp,

            // Boost
            'is_boosted' => (bool) $this->is_boosted,
            'boost_score' => (int) $this->boost_score,
            'boost_expires_at' => $this->boost_expires_at?->timestamp,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        // N'indexer que les annonces visibles, publiquement listées et non supprimées
        return $this->is_visible && in_array($this->status, self::PUBLIC_STATUSES, true) && !$this->trashed();
    }

    /**
     * Get the name of the publisher (Agency name or User name).
     */
    public function getPublisherName(): string
    {
        $user = $this->user;

        // Si l'utilisateur est de type AGENCY, on essaie de retourner le nom de son agence
        if ($user && $user->type === UserType::AGENCY) {
            $agency = $this->agency;
            if ($agency instanceof Agency) {
                return $agency->name;
            }

            $userAgency = $user->agency;
            if ($userAgency instanceof Agency) {
                return $userAgency->name;
            }
        }

        // Sinon ou par défaut, on retourne le nom personnel
        return $user ? "{$user->firstname} {$user->lastname}" : 'Anonyme';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<Quarter, $this>
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<AdReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(AdReport::class);
    }

    /** @return HasMany<UnlockedAd, $this> */
    public function unlockedAds(): HasMany
    {
        return $this->hasMany(UnlockedAd::class);
    }

    /** @return HasMany<AdInteraction, $this> */
    public function interactions(): HasMany
    {
        return $this->hasMany(AdInteraction::class);
    }

    /** @return HasMany<AdInteraction, $this> */
    public function views(): HasMany
    {
        return $this->hasMany(AdInteraction::class)->where('type', AdInteraction::TYPE_VIEW);
    }

    /** Get the number of views in the last 30 days. */
    public function recentViewCount(): int
    {
        return $this->interactions()
            ->where('type', AdInteraction::TYPE_VIEW)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
    }

    /** Check if a user has favorited this ad. */
    public function isFavoritedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $favorites = AdInteraction::where('user_id', $user->id)
            ->where('ad_id', $this->id)
            ->where('type', AdInteraction::TYPE_FAVORITE)
            ->count();

        $unfavorites = AdInteraction::where('user_id', $user->id)
            ->where('ad_id', $this->id)
            ->where('type', AdInteraction::TYPE_UNFAVORITE)
            ->count();

        return $favorites > $unfavorites;
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<LeaseContract, $this> */
    public function leaseContracts(): HasMany
    {
        return $this->hasMany(LeaseContract::class);
    }

    /**
     * @return BelongsTo<AdType, $this>
     */
    public function ad_type(): BelongsTo
    {
        return $this->belongsTo(AdType::class, 'type_id');
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['quarter.city', 'ad_type']);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->onlyKeepLatest(10);

        $this->addMediaCollection('property_condition')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Tiny blur placeholder — sync, ~20px, used as blurDataURL before the real image loads.
        $this->addMediaConversion('placeholder')
            ->nonQueued()
            ->fit(Fit::Max, 20, 20)
            ->format('webp')
            ->quality(30);

        // Listing card thumbnail — landscape-safe, no upscaling, queued.
        $this->addMediaConversion('thumb')
            ->queued()
            ->fit(Fit::Max, 480, 320)
            ->format('webp')
            ->quality(78);

        // Detail view & lightbox — high quality, landscape-safe, queued.
        $this->addMediaConversion('large')
            ->queued()
            ->fit(Fit::Max, 1280, 854)
            ->format('webp')
            ->quality(82);
    }

    /**
     * Check if the ad is unlocked for a specific user.
     *
     * Result is memoized per request in a static array to avoid N+1 queries
     * when AdResource calls this method multiple times for the same ad/user pair.
     */
    public function isUnlockedFor(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Owner always has access
        if ($this->user_id === $user->id) {
            return true;
        }

        /** @var array<string, bool> $cache */
        static $cache = [];

        $key = $user->id.':'.$this->id;

        if (!array_key_exists($key, $cache)) {
            $cache[$key] = UnlockedAd::where('user_id', $user->id)
                ->where('ad_id', $this->id)
                ->exists();
        }

        return $cache[$key];
    }

    /**
     * Get all images for the ad (images are always visible).
     */
    public function getAccessibleImages(?User $user): Collection
    {
        $media = $this->getMedia('images');

        if ($this->isUnlockedFor($user)) {
            return $media;
        }

        return $media->take(1);
    }

    /**
     * Boost this ad with a given score and duration
     */
    public function boost(int $score, int $durationDays): void
    {
        $this->forceFill([
            'is_boosted' => true,
            'boost_score' => $score,
            'boost_expires_at' => now()->addDays($durationDays),
            'boosted_at' => now(),
        ])->save();
    }

    /**
     * Remove boost from this ad
     */
    public function unboost(): void
    {
        $this->forceFill([
            'is_boosted' => false,
            'boost_score' => 0,
            'boost_expires_at' => null,
        ])->save();
    }

    /**
     * Check if ad is currently boosted
     */
    public function isBoosted(): bool
    {
        return $this->is_boosted
            && $this->boost_expires_at
            && $this->boost_expires_at->isFuture();
    }

    /**
     * Scope to get only boosted ads
     */
    #[Scope]
    protected function boosted($query)
    {
        return $query->where('is_boosted', true)
            ->where('boost_expires_at', '>', now());
    }

    /**
     * Scope to order by boost score then created_at
     */
    #[Scope]
    protected function orderByBoost($query)
    {
        return $query->orderByDesc('boost_score')
            ->orderByDesc('created_at');
    }

    /**
     * Scope to get only visible ads
     */
    #[Scope]
    protected function visible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope to get only publicly listed ads (available + reserved).
     */
    #[Scope]
    protected function publiclyListed($query)
    {
        return $query->whereIn('status', array_map(fn (AdStatus $s) => $s->value, self::PUBLIC_STATUSES));
    }

    /**
     * Scope to get only currently available ads based on date range
     */
    #[Scope]
    protected function currentlyAvailable($query)
    {
        $today = now()->toDateString();

        return $query
            ->where(function ($q) use ($today): void {
                $q->whereNull('available_from')
                    ->orWhere('available_from', '<=', $today);
            })
            ->where(function ($q) use ($today): void {
                $q->whereNull('available_to')
                    ->orWhere('available_to', '>=', $today);
            });
    }

    /**
     * Scope to filter by property attributes
     */
    #[Scope]
    protected function withAttributes($query, array $attributes)
    {
        foreach ($attributes as $attribute) {
            $query->whereJsonContains('attributes', $attribute);
        }

        return $query;
    }

    /**
     * Set availability period
     */
    public function setAvailability(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): void
    {
        $this->update([
            'available_from' => $from,
            'available_to' => $to,
        ]);
    }

    /**
     * Check if ad is currently available based on date range
     */
    public function isCurrentlyAvailable(): bool
    {
        $today = now()->toDateString();

        if ($this->available_from && $this->available_from->toDateString() > $today) {
            return false;
        }

        if ($this->available_to && $this->available_to->toDateString() < $today) {
            return false;
        }

        return true;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['location'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Annonce « {$this->title} » {$eventName}");
    }
}
