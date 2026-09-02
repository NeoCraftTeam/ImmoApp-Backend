<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SponsorshipTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per ad rendered in the sponsored feed.
 *
 * Append-only telemetry. Powers SponsorshipAnalyticsService and the
 * SponsorshipTierStats admin widget.
 *
 * @property string $id
 * @property string $ad_id
 * @property string|null $user_id
 * @property SponsorshipTier $tier
 * @property int $slot
 * @property Carbon $shown_at
 * @property-read Ad $ad
 * @property-read User|null $user
 */
class SponsoredImpression extends Model
{
    use HasUuids;
    use MassPrunable;

    public $timestamps = false;

    protected $fillable = [
        'ad_id',
        'user_id',
        'tier',
        'slot',
        'shown_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'tier' => SponsorshipTier::class,
            'slot' => 'integer',
            'shown_at' => 'datetime',
        ];
    }

    /**
     * Prune impressions older than 90 days — analytics dashboards only
     * look at the trailing 30 days, so we keep a small buffer for ad-hoc
     * back-fill queries and drop the rest to keep the table fast.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('shown_at', '<', now()->subDays(90));
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
}
