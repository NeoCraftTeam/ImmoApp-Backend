<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdBoostStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $ad_id
 * @property string $user_id
 * @property string $boost_pack_id
 * @property int $credits_spent
 * @property int $boost_score
 * @property int $duration_days
 * @property Carbon $started_at
 * @property Carbon $expires_at
 * @property AdBoostStatus $status
 */
class AdBoost extends Model
{
    use HasUuids;

    protected $fillable = [
        'ad_id',
        'user_id',
        'boost_pack_id',
        'credits_spent',
        'boost_score',
        'duration_days',
        'started_at',
        'expires_at',
        'status',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'credits_spent' => 'integer',
            'boost_score' => 'integer',
            'duration_days' => 'integer',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => AdBoostStatus::class,
        ];
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<BoostPack, $this> */
    public function boostPack(): BelongsTo
    {
        return $this->belongsTo(BoostPack::class);
    }

    #[Scope]
    protected function active($query): void
    {
        $query->where('status', AdBoostStatus::Active)->where('expires_at', '>', now());
    }

    #[Scope]
    protected function expired($query): void
    {
        $query->where('status', AdBoostStatus::Active)->where('expires_at', '<=', now());
    }
}
