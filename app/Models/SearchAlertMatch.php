<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buffer record that holds a single ad–alert match waiting to be included
 * in the next digest notification.
 *
 * @property string $id
 * @property string $search_alert_id
 * @property string $user_id
 * @property string $ad_id
 * @property Carbon $matched_at
 * @property Carbon|null $digest_sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class SearchAlertMatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'search_alert_id',
        'user_id',
        'ad_id',
        'matched_at',
        'digest_sent_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'matched_at' => 'datetime',
            'digest_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SearchAlert, $this> */
    public function searchAlert(): BelongsTo
    {
        return $this->belongsTo(SearchAlert::class);
    }

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

    /** Scope: only records not yet included in a digest. */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('digest_sent_at');
    }
}
