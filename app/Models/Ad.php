<?php

namespace App\Models;

use App\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    public function isHosuse(): bool
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

    protected function images(): hasMany
    {
        return $this->hasMany(AdImage::class);
    }

    protected function reviews(): hasMany
    {
        return $this->hasMany(Review::class);
    }
}
