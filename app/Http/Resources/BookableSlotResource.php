<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BookableSlotResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'starts_at' => $this->resource['starts_at'] ?? null,
            'ends_at' => $this->resource['ends_at'] ?? null,
            'is_available' => $this->resource['is_available'] ?? true,
        ];
    }
}
