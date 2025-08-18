<?php

namespace App\Models;

use Database\Factories\UnlockedAdFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read Ad|null $ad
 * @property-read Payment|null $payment
 * @property-read User|null $user
 * @method static UnlockedAdFactory factory($count = null, $state = [])
 * @method static Builder<static>|UnlockedAd newModelQuery()
 * @method static Builder<static>|UnlockedAd newQuery()
 * @method static Builder<static>|UnlockedAd onlyTrashed()
 * @method static Builder<static>|UnlockedAd query()
 * @method static Builder<static>|UnlockedAd withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|UnlockedAd withoutTrashed()
 * @mixin Eloquent
 */
class UnlockedAd extends Model
{
    use HasFactory, softDeletes;

    public $timestamps = false;

    protected $fillable = ['ad_id', 'user_id', 'payment_id',];

    protected $hidden = ['unlocked_at', 'updated_at', 'deleted_at'];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return ['unlocked_at' => 'timestamp', 'updated_at' => 'timestamp',];
    }
}
