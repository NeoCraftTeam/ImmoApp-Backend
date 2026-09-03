<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisputeEvidenceType;
use Database\Factories\DisputeEvidenceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $dispute_id
 * @property string $uploader_id
 * @property DisputeEvidenceType $type
 * @property string $disk
 * @property string $path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DisputeEvidence extends Model
{
    /** @use HasFactory<DisputeEvidenceFactory> */
    use HasFactory, HasUuids;

    protected $table = 'dispute_evidences';

    protected $fillable = [
        'dispute_id',
        'uploader_id',
        'type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'type' => DisputeEvidenceType::class,
            'size_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<Dispute, $this> */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function url(int $ttlMinutes = 30): ?string
    {
        $disk = Storage::disk($this->disk);

        try {
            return $disk->temporaryUrl($this->path, now()->addMinutes($ttlMinutes));
        } catch (\Throwable) {
            // Disk driver does not support signed URLs (e.g. local) — fall back to public URL.
        }

        return $disk->url($this->path);
    }
}
