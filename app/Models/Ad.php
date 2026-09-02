<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdStatus;
use App\Enums\SponsorshipTier;
use App\Enums\TransactionType;
use App\Enums\VerificationStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Concerns\AdSearchable;
use App\Models\Concerns\HasBoostState;
use App\Models\Concerns\HasPropertyAttributes;
use App\Models\Concerns\HasSponsorshipTier;
use App\Models\Concerns\HasVisibility;
use App\Models\Concerns\InteractsWithAudience;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\AdFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Zap\Models\Concerns\HasSchedules;

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
 * @property TransactionType|null $transaction_type
 * @property SponsorshipTier|null $subscription_tier
 * @property VerificationStatus|null $verification_status
 * @property array<string, mixed>|null $tour_config
 * @property array<string, mixed>|null $draft_payload
 * @property array<string, mixed>|null $prescreening_questions
 * @property Carbon|null $expires_at
 * @property Carbon|null $available_from
 * @property Carbon|null $available_to
 * @property Carbon|null $boost_expires_at
 * @property Carbon|null $boosted_at
 * @property Carbon|null $last_shown_at
 * @property Carbon|null $tour_published_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $verification_requested_at
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
class Ad extends Model implements HasMedia
{
    use AdSearchable, HasBoostState, HasPropertyAttributes, HasSponsorshipTier, HasVisibility, InteractsWithAudience, InteractsWithMedia, Searchable;
    use HasFactory, HasSchedules, HasUuids, LogsActivity, SoftDeletes;

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
        'price_period',
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
        'transaction_type',
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
        // SEC-007: is_verified, verified_at, verification_status, verification_notes,
        // verification_requested_at excluded — use forceFill() in admin/verification flows only.
        'draft_payload',
        'prescreening_questions',
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

    #[\Override]
    protected function casts(): array
    {
        return [
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
            'is_subscription_sponsored' => 'boolean',
            'subscription_tier' => SponsorshipTier::class,
            'last_shown_at' => 'datetime',
            'impression_count' => 'integer',
            'charges_forfaitaires' => 'boolean',
            'charges_montant_forfait' => 'integer',
            'charges_eau' => 'integer',
            'charges_electricite' => 'integer',
            'transaction_type' => TransactionType::class,
            'has_3d_tour' => 'boolean',
            'tour_config' => 'array',
            'tour_published_at' => 'datetime',
            'draft_payload' => 'array',
            'prescreening_questions' => 'array',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'verification_status' => VerificationStatus::class,
            'verification_requested_at' => 'datetime',
        ];
    }

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
            // Only regenerate the slug on title edits BEFORE the ad has
            // been published. Post-publish, the slug is a stable SEO URL
            // — typo fixes on the title shouldn't break inbound links or
            // bust CDN canonicals. Saves the bulk of `exists()` calls
            // because most title edits happen post-PENDING.
            if (!$ad->isDirty('title')) {
                return;
            }

            $original = $ad->getOriginal('status');
            $previousStatus = $original instanceof AdStatus
                ? $original
                : AdStatus::tryFrom((string) $original);

            if ($previousStatus !== null && $previousStatus !== AdStatus::PENDING) {
                return;
            }

            $ad->slug = self::generateUniqueSlug($ad->title, $ad->id);
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

    /** Return the number of 360° scenes in the tour config. */
    public function getTourScenesCountAttribute(): int
    {
        if (!$this->tour_config) {
            return 0;
        }

        return count($this->tour_config['scenes'] ?? []);
    }

    /**
     * Scope — ads that have a published 3D tour.
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
        $base = Str::slug($title);

        // Bound the linear `exists()` probe — try the base slug and the
        // next few numeric suffixes, then fall back to a random suffix.
        // Common-title bulk imports (e.g. "Appartement à louer Douala")
        // used to fire 5-10 `exists()` queries per insertion; this caps
        // the worst case at 4 queries.
        foreach ([null, 1, 2, 3] as $suffix) {
            $candidate = $suffix === null ? $base : "{$base}-{$suffix}";
            $exists = self::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if (!$exists) {
                return $candidate;
            }
        }

        // Past three collisions, append a short random suffix and trust
        // that 36^6 collisions are vanishingly unlikely.
        return $base.'-'.Str::lower(Str::random(6));
    }

    /**
     * Scout hook — delegates to {@see AdSearchable::buildSearchableArray()}.
     * Kept on the model so it wins over the Searchable trait default.
     */
    public function toSearchableArray(): array
    {
        return $this->buildSearchableArray();
    }

    public function shouldBeSearchable(): bool
    {
        // N'indexer que les annonces visibles, publiquement listées et non supprimées
        return $this->is_visible && in_array($this->status, self::PUBLIC_STATUSES, true) && !$this->trashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
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

    /** @return HasMany<AdBoost, $this> */
    public function adBoosts(): HasMany
    {
        return $this->hasMany(AdBoost::class);
    }

    /**
     * @return BelongsTo<AdType, $this>
     */
    public function ad_type(): BelongsTo
    {
        return $this->belongsTo(AdType::class, 'type_id');
    }

    /**
     * Scout hook — delegates to {@see AdSearchable::eagerLoadForSearch()}.
     * Kept on the model so it wins over the Searchable trait default.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $this->eagerLoadForSearch($query);
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
     * Scope — the canonical public listing query: visible + publicly listed
     * ads, eager-loaded with exactly the relations and review aggregates
     * AdResource renders. Shared by the paginated index and the cursor feed
     * (including the organic-boost enrichment) so the base query lives in one
     * place instead of being duplicated at each call site.
     */
    #[Scope]
    protected function forPublicListing($query)
    {
        return $query
            ->with('quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'user.media', 'user.latestTrustScore', 'agency')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->visible()
            ->publiclyListed();
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

    /**
     * Scope to order by sponsorship ranking with distribution strategy.
     *
     * This ensures 60% sponsored ads, 40% organic in the feed.
     */
    #[Scope]
    protected function orderBySponsorship($query)
    {
        return $query
            ->orderByDesc('is_subscription_sponsored')
            ->orderByDesc('boost_score')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
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
