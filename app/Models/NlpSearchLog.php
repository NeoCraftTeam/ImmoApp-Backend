<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Forensic + quota log for natural-language search calls.
 *
 * Created by NaturalSearchController::parse(). Used by:
 *  - rate limiter `ai_search.daily` for per-IP / per-user 24h ceiling
 *  - Filament admin (later) for abuse investigation
 *  - Prunable (`model:prune` daily) — 30 day retention
 */
final class NlpSearchLog extends Model
{
    use HasUuids;
    use Prunable;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ip',
        'context',
        'query',
        'display_currency',
        'success_provider',
        'parsed',
        'latency_ms',
        'created_at',
    ];

    protected $casts = [
        'parsed' => 'array',
        'latency_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Model::prune() target — drop rows older than 30 days.
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(30));
    }
}
