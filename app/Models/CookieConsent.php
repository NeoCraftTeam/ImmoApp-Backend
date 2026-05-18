<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * CNIL-compliant cookie consent record.
 * Stores the proof of consent for each preference save (accept / reject / custom).
 *
 * @property string $id
 * @property string|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $policy_version
 * @property bool $analytics
 * @property bool $marketing
 * @property Carbon $consented_at
 */
final class CookieConsent extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'policy_version',
        'analytics',
        'marketing',
        'consented_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'analytics' => 'boolean',
            'marketing' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
