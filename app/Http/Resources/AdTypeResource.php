<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AdType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdType */
final class AdTypeResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'desc' => $this->desc,
        ];
    }
}
