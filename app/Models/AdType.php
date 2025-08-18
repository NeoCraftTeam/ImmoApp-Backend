<?php

namespace App\Models;

use Database\Factories\AdTypeFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $desc
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @method static AdTypeFactory factory($count = null, $state = [])
 * @method static Builder<static>|AdType newModelQuery()
 * @method static Builder<static>|AdType newQuery()
 * @method static Builder<static>|AdType onlyTrashed()
 * @method static Builder<static>|AdType query()
 * @method static Builder<static>|AdType whereCreatedAt($value)
 * @method static Builder<static>|AdType whereDeletedAt($value)
 * @method static Builder<static>|AdType whereDesc($value)
 * @method static Builder<static>|AdType whereId($value)
 * @method static Builder<static>|AdType whereName($value)
 * @method static Builder<static>|AdType whereUpdatedAt($value)
 * @method static Builder<static>|AdType withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|AdType withoutTrashed()
 * @mixin Eloquent
 */
class AdType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ad_type';

    protected $fillable = [
        'name',
        'desc',
    ];

    protected function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
