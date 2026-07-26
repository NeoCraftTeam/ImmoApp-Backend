<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScreeningDocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property ScreeningDocumentType $document_type
 */
class TenantScreeningDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'screening_request_id',
        'document_type',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'notes',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'document_type' => ScreeningDocumentType::class,
            'size_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<TenantScreeningRequest, $this> */
    public function screeningRequest(): BelongsTo
    {
        return $this->belongsTo(TenantScreeningRequest::class, 'screening_request_id');
    }

    public function url(): string
    {
        $disk = Storage::disk($this->disk);

        try {
            return $disk->temporaryUrl($this->path, now()->addMinutes(30));
        } catch (\Throwable) {
            // Local disk does not support signed URLs — fall back to public URL.
        }

        return $disk->url($this->path);
    }
}
