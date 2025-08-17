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
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'user_id' => $this->user_id,
            'quarter_id' => $this->quarter_id,
            'type_id' => $this->type_id,

            'user' => new UserResource($this->whenLoaded('user')), 
            'quarter' => new QuarterResource($this->whenLoaded('quarter')),
            'type' => new AdTypeResource($this->whenLoaded('type')),
        ];
    }
}
