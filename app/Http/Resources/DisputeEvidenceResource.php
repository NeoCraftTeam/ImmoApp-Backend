<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DisputeEvidence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DisputeEvidence
 */
class DisputeEvidenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dispute_id' => $this->dispute_id,
            'uploader_id' => $this->uploader_id,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'url' => $this->url(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
