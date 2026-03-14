<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVisit extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'source',
        'referrer_domain',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'user_id',
        'ip_hash',
        'device_type',
        'visited_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }
}
