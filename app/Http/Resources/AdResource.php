<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ad */
final class AdResource extends JsonResource
{
    /**
     * @param  Ad  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    #[\Override]
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
            'location' => $this->location ? [
                'latitude' => $this->location->getY(),
                'longitude' => $this->location->getX(),
            ] : null,
            'status' => $this->status,
            'is_unlocked' => $this->isUnlockedFor($request->user()),
            'total_images' => $this->getMedia('images')->count(),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'user' => new UserResource($this->whenLoaded('user')),
            'agency' => new AgencyResource($this->whenLoaded('agency')),
            'published_by' => $this->getPublisherName(),
            'quarter' => new QuarterResource($this->whenLoaded('quarter')),
            'type' => new AdTypeResource($this->whenLoaded('ad_type')),
            'images' => $this->getAccessibleImages($request->user())->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumb' => $media->getUrl('thumb'),
                'mime_type' => $media->mime_type,
                'is_primary' => $this->getMedia('images')->first()?->id === $media->id,
            ]),
        ];
    }
}
