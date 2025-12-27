<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Ad|null $ad
 * @property-read User|null $user
 *
 * @method static ReviewFactory factory($count = null, $state = [])
 * @method static Builder<static>|Review newModelQuery()
 * @method static Builder<static>|Review newQuery()
 * @method static Builder<static>|Review onlyTrashed()
 * @method static Builder<static>|Review query()
 * @method static Builder<static>|Review withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Review withoutTrashed()
 *
 * @property string $id
 * @property string $rating
 * @property string|null $comment
 * @property string $ad_id
 * @property string $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder<static>|Review whereAdId($value)
 * @method static Builder<static>|Review whereComment($value)
 * @method static Builder<static>|Review whereCreatedAt($value)
 * @method static Builder<static>|Review whereDeletedAt($value)
 * @method static Builder<static>|Review whereId($value)
 * @method static Builder<static>|Review whereRating($value)
 * @method static Builder<static>|Review whereUpdatedAt($value)
 * @method static Builder<static>|Review whereUserId($value)
 *
 * @mixin Eloquent
 */
class Review extends Model
{
    use HasFactory, HasUuids, softDeletes;

    protected $fillable = ['rating', 'comment', 'ad_id', 'user_id', 'agency_id'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
