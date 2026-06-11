<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TenantScreeningDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TenantScreeningDocument */
final class TenantScreeningDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type->value,
            'document_type_label' => $this->document_type->getLabel(),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'notes' => $this->notes,
            'url' => $this->url(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
