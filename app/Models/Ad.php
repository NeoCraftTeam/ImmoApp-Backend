<?php

namespace App\Models;


use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\AdFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;


/**
 * @property-read Quarter|null $quarter
 * @property-read User|null $user
 * @method static AdFactory factory($count = null, $state = [])
 * @method static Builder<static>|Ad newModelQuery()
 * @method static Builder<static>|Ad newQuery()
 * @method static Builder<static>|Ad onlyTrashed()
 * @method static Builder<static>|Ad query()
 * @method static Builder<static>|Ad withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Ad withoutTrashed()
 * @mixin Eloquent
 */
class Ad extends Model
{
    use HasFactory, softDeletes;


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
        'type_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'location' => Point::class, // Assuming 'point' is a custom cast for PostGIS
        'has_parking' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($ad) {
            if (empty($ad->slug)) { // <-- garantie que même null sera généré
                $ad->slug = self::generateUniqueSlug($ad->title);
            }
        });

        static::updating(function ($ad) {
            if ($ad->isDirty('title')) {
                $ad->slug = self::generateUniqueSlug($ad->title, $ad->id);
            }
        });
    }

    // Génère automatiquement un slug unique avant de sauvegarder

    public static function generateUniqueSlug(string $title, int $ignoreId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $i = 1;

        while (self::where('slug', $slug)
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $original . '-' . $i;
            $i++;
        }

        return $slug;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }


    protected function images(): hasMany
    {
        return $this->hasMany(AdImage::class);
    }

    protected function reviews(): hasMany
    {
        return $this->hasMany(Review::class);
    }

    protected function ad_type(): BelongsTo
    {
        return $this->belongsTo(AdType::class);
    }
}
