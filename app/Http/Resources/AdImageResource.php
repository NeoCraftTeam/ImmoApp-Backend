<?php

namespace App\Http\Resources;

use App\Models\AdImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdImage */
class AdImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image_path' => $this->image_path,
            'url' => $this->url,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'ad_id' => $this->ad_id,

            'ad' => new AdResource($this->whenLoaded('ad')),
        ];
    }
}
