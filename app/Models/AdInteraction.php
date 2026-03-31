<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks user interactions with ads (views, favorites, searches, unlocks).
 *
 * Used by the RecommendationEngine to build user profiles and score ads.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $ad_id
 * @property string $type
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Ad|null $ad
 */
class AdInteraction extends Model
{
    use HasUuids;
    use MassPrunable;

    /**
     * Prune interactions older than 2 years.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subYears(2));
    }

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ad_id',
        'type',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Interaction Types ─────────────────────────────────────────────

    public const TYPE_VIEW = 'view';

    public const TYPE_FAVORITE = 'favorite';

    public const TYPE_UNFAVORITE = 'unfavorite';

    public const TYPE_SEARCH = 'search';

    public const TYPE_UNLOCK = 'unlock';

    public const TYPE_IMPRESSION = 'impression';

    public const TYPE_SHARE = 'share';

    public const TYPE_CONTACT_CLICK = 'contact_click';

    public const TYPE_PHONE_CLICK = 'phone_click';

    // ── Relations ─────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}
