<?php

namespace App\Http\Resources;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ad */
class AdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'adresse' => $this->adresse,
            'price' => $this->price,
            'surface_area' => $this->surface_area,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'has_parking' => $this->has_parking,
            'location' => $this->location,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'user' => new UserResource($this->whenLoaded('user')),
            'quarter' => new QuarterResource($this->whenLoaded('quarter')),
            'type' => new AdTypeResource($this->whenLoaded('ad_type')),
            'images' => $this->when($this->relationLoaded('images'), function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'path' => $image->image_path,
                        'url' => Storage::url($image->image_path),
                        'full_url' => url(Storage::url($image->image_path)),
                        'is_primary' => (bool)$image->is_primary,
                    ];
                })->values();
            }, []),
        ];
    }
}
