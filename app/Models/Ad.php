<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\AdFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
/**
 * @property-read Quarter|null $quarter
 * @property-read User|null $user
 *
 * @method static AdFactory factory($count = null, $state = [])
 * @method static Builder<static>|Ad newModelQuery()
 * @method static Builder<static>|Ad newQuery()
 * @method static Builder<static>|Ad onlyTrashed()
 * @method static Builder<static>|Ad query()
 * @method static Builder<static>|Ad withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Ad withoutTrashed()
 *
 * @property int $id
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
 * @property string $status
 * @property string|null $expires_at
 * @property int $user_id
 * @property int $quarter_id
 * @property int $type_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Ad extends Model implements HasMedia
{
    use HasFactory, HasUuids, SoftDeletes;
    use InteractsWithMedia, Searchable;

    protected $table = 'ad';

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
        'status',
        'expires_at',
        'user_id',
        'quarter_id',
        'type_id',
    ];

    protected $hidden = [
        'location',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'location' => Point::class, // Assuming 'point' is a custom cast for PostGIS
        'status' => \App\Enums\AdStatus::class,
        'has_parking' => 'boolean',
        'expires_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($ad): void {
            if (empty($ad->slug)) { // <-- garantie que même null sera généré
                $ad->slug = self::generateUniqueSlug($ad->title);
            }
        });

        static::updating(function ($ad): void {
            if ($ad->isDirty('title')) {
                $ad->slug = self::generateUniqueSlug($ad->title, $ad->id);
            }
        });
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

            // Relations — vérifier qu'elles existent
            'city' => $this->quarter?->city?->name,
            'quarter' => $this->quarter?->name,
            'type' => $this->ad_type?->name,
            'type_id' => $this->type_id,
            'quarter_id' => $this->quarter_id,

            // Pour la recherche géographique (optionnel)
            '_geo' => $this->location ? [
                'lat' => $this->location->getLatitude(),
                'lng' => $this->location->getLongitude(),
            ] : null,

            'created_at' => $this->created_at?->timestamp,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        // N'indexer que les annonces non supprimées et actives
        return $this->status === 'available' && ! $this->trashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }

    public function reviews(): hasMany
    {
        return $this->hasMany(Review::class);
    }

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
            ->onlyKeepLatest(10)
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->nonQueued(); // Fast generation for immediate API response
    }

    /**
     * Check if the ad is unlocked for a specific user.
     */
    public function isUnlockedFor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Owner always has access
        if ($this->user_id === $user->id) {
            return true;
        }

        // Check for successful unlock payment (assuming Payment model links user and ad)
        return Payment::where('user_id', $user->id)
            ->where('ad_id', $this->id)
            ->where('type', 'unlock') // Assuming 'unlock' is the enum value or string
            ->where('status', 'success') // Assuming 'success' is the enum value
            ->exists();
    }

    /**
     * Get images accessible to the current user context.
     */
    public function getAccessibleImages(?User $user): \Illuminate\Support\Collection
    {
        $media = $this->getMedia('images');

        if ($media->isEmpty()) {
            return collect();
        }

        if ($this->isUnlockedFor($user)) {
            return $media;
        }

        // Return only the first image (primary)
        return $media->take(1);
    }
}
