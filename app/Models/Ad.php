<?php

namespace App\Models;

use App\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;


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
        'property_type',
        'surface_area',
        'bedrooms',
        'bathrooms',
        'has_parking',
        'latitude',
        'longitude',
        'status',
        'user_id',
        'quarter_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
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

    public static function generateUniqueSlug(string $title, int $ignoreId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $i = 1;

        while (self::where('slug', $slug)
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
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

    /**
     * Returns true is the property is an apartment.
     *
     */
    public function isApartment(): bool
    {
        return $this->type === PropertyType::APARTMENT;
    }

    /**
     * Returns true is the property is a house.
     *
     */
    public function isHouse(): bool
    {
        return $this->type === PropertyType::HOUSE;
    }

    /**
     * Returns true is the property is a land.
     *
     */
    public function isLand(): bool
    {
        return $this->type === PropertyType::LAND;
    }

    /**
     * Returns true is the property is a studio.
     *
     */
    public function isStudio(): bool
    {
        return $this->type === PropertyType::STUDIO;
    }

    protected function casts(): array
    {
        return [
            'has_parking' => 'boolean',
            'property_type' => PropertyType::class,
        ];
    }

    // Génère automatiquement un slug unique avant de sauvegarder

    protected function images(): hasMany
    {
        return $this->hasMany(AdImage::class);
    }

    protected function reviews(): hasMany
    {
        return $this->hasMany(Review::class);
    }
}
