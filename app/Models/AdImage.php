<?php

namespace App\Models;

use Database\Factories\AdImageFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read Ad|null $ad
 * @method static AdImageFactory factory($count = null, $state = [])
 * @method static Builder<static>|AdImage newModelQuery()
 * @method static Builder<static>|AdImage newQuery()
 * @method static Builder<static>|AdImage onlyTrashed()
 * @method static Builder<static>|AdImage query()
 * @method static Builder<static>|AdImage withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|AdImage withoutTrashed()
 * @mixin Eloquent
 */
class AdImage extends Model
{
    use HasFactory, softDeletes;

    protected $fillable = [
        'image_path',
        'ad_id',
    ];


    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}
