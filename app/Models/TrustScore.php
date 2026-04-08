<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrustScoreTier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $role_context
 * @property int $score
 * @property TrustScoreTier $tier
 * @property array<string, mixed> $components
 * @property Carbon $computed_at
 */
final class TrustScore extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'role_context',
        'score',
        'tier',
        'components',
        'computed_at',
    ];

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'tier' => TrustScoreTier::class,
            'components' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
