<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Temporary artifact for admin panel exports generated asynchronously (queue).
 *
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $disk
 * @property-read string $path
 * @property-read string $download_name
 * @property-read string $mime_type
 * @property-read Carbon $expires_at
 */
class AdminQueuedExport extends Model
{
    use HasUuids;
    use Prunable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'disk',
        'path',
        'download_name',
        'mime_type',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    protected function pruning(): void
    {
        if ($this->path === '') {
            return;
        }

        $disk = Storage::disk($this->disk);

        if ($disk->exists($this->path)) {
            $disk->delete($this->path);
        }
    }
}
