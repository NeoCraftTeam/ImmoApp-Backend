<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UnlockedAdFactory;
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
 * @property-read Payment|null $payment
 * @property-read User|null $user
 *
 * @method static UnlockedAdFactory factory($count = null, $state = [])
 * @method static Builder<static>|UnlockedAd newModelQuery()
 * @method static Builder<static>|UnlockedAd newQuery()
 * @method static Builder<static>|UnlockedAd onlyTrashed()
 * @method static Builder<static>|UnlockedAd query()
 * @method static Builder<static>|UnlockedAd withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|UnlockedAd withoutTrashed()
 *
 * @property string $id
 * @property string $ad_id
 * @property string $user_id
 * @property string $payment_id
 * @property Carbon|null $unlocked_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder<static>|UnlockedAd whereAdId($value)
 * @method static Builder<static>|UnlockedAd whereDeletedAt($value)
 * @method static Builder<static>|UnlockedAd whereId($value)
 * @method static Builder<static>|UnlockedAd wherePaymentId($value)
 * @method static Builder<static>|UnlockedAd whereUnlockedAt($value)
 * @method static Builder<static>|UnlockedAd whereUpdatedAt($value)
 * @method static Builder<static>|UnlockedAd whereUserId($value)
 *
 * @mixin Eloquent
 */
class UnlockedAd extends Model
{
    use HasFactory, HasUuids, softDeletes;

    public $timestamps = false;

    protected $table = 'unlocked_ads';

    protected $fillable = ['ad_id', 'user_id', 'payment_id'];

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
        return ['unlocked_at' => 'timestamp', 'updated_at' => 'timestamp'];
    }
}
