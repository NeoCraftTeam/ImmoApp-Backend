<?php

namespace App\Models;

use Database\Factories\AdImageFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property-read Ad|null $ad
 * @method static AdImageFactory factory($count = null, $state = [])
 * @method static Builder<static>|AdImage newModelQuery()
 * @method static Builder<static>|AdImage newQuery()
 * @method static Builder<static>|AdImage onlyTrashed()
 * @method static Builder<static>|AdImage query()
 * @method static Builder<static>|AdImage withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|AdImage withoutTrashed()
 * @property int $id
 * @property string $image_path
 * @property int $ad_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property bool $is_primary
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @method static Builder<static>|AdImage whereAdId($value)
 * @method static Builder<static>|AdImage whereCreatedAt($value)
 * @method static Builder<static>|AdImage whereDeletedAt($value)
 * @method static Builder<static>|AdImage whereId($value)
 * @method static Builder<static>|AdImage whereImagePath($value)
 * @method static Builder<static>|AdImage whereIsPrimary($value)
 * @method static Builder<static>|AdImage whereUpdatedAt($value)
 * @mixin Eloquent
 */
class AdImage extends Model implements HasMedia
{
    use HasFactory, softDeletes;
    use InteractsWithMedia;

    protected $fillable = [
        'ad_id',
        'is_primary',
    ];


    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class, 'ad_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile(true); // Une seule image par AdImage
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('medium')
            ->width(600)
            ->height(400)
            ->quality(80)
            ->nonQueued();

        $this->addMediaConversion('large')
            ->width(1200)
            ->height(800)
            ->quality(85)
            ->nonQueued();
    }

    // Helper pour récupérer l'URL de l'image
    public function getImageUrl($conversion = null)
    {
        $media = $this->getFirstMedia('images');
        if (!$media) {
            return null;
        }

        return $conversion ? $media->getUrl($conversion) : $media->getUrl();
    }
}
