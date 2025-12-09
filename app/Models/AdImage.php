<?php

namespace App\Models;

use Database\Factories\AdImageFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Ad|null $ad
 *
 * @method static AdImageFactory factory($count = null, $state = [])
 * @method static Builder<static>|AdImage newModelQuery()
 * @method static Builder<static>|AdImage newQuery()
 * @method static Builder<static>|AdImage onlyTrashed()
 * @method static Builder<static>|AdImage query()
 * @method static Builder<static>|AdImage withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|AdImage withoutTrashed()
 *
 * @property int $id
 * @property string $image_path
 * @property int $ad_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property bool $is_primary
 *
 * @method static Builder<static>|AdImage whereAdId($value)
 * @method static Builder<static>|AdImage whereCreatedAt($value)
 * @method static Builder<static>|AdImage whereDeletedAt($value)
 * @method static Builder<static>|AdImage whereId($value)
 * @method static Builder<static>|AdImage whereImagePath($value)
 * @method static Builder<static>|AdImage whereIsPrimary($value)
 * @method static Builder<static>|AdImage whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class AdImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ad_images';

    protected $fillable = [
        'ad_id',
        'image_path',
        'is_primary',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class, 'ad_id', 'id');
    }

    /**
     * Get the full public URL to the stored image.
     */
    public function getUrlAttribute()
    {
        return \Storage::url($this->image_path);
    }
}
