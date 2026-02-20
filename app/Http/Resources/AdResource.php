<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
        $user = $request->user();

        // Compute rating from eager-loaded aggregate or fallback
        $avgRating = $this->reviews_avg_rating
            ?? ($this->relationLoaded('reviews') && $this->reviews->count() > 0
                ? round($this->reviews->avg('rating'), 1)
                : null);
        $reviewsCount = $this->reviews_count
            ?? ($this->relationLoaded('reviews') ? $this->reviews->count() : 0);

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
            'is_visible' => $this->is_visible,
            'available_from' => $this->available_from?->format('Y-m-d'),
            'available_to' => $this->available_to?->format('Y-m-d'),
            'attributes' => $this->attributes ?? [],
            'is_currently_available' => $this->isCurrentlyAvailable(),
            'is_unlocked' => $this->isUnlockedFor($user),
            'total_images' => $this->getMedia('images')->count(),
            'rating' => $avgRating ? (float) $avgRating : null,
            'reviews_count' => (int) $reviewsCount,
            'is_favorited' => $this->isFavoritedBy($user),
            'view_count' => $this->views_count ?? 0,

            // Premium info - only visible when unlocked
            'deposit_amount' => $this->when($this->isUnlockedFor($user), $this->deposit_amount),
            'minimum_lease_duration' => $this->when($this->isUnlockedFor($user), $this->minimum_lease_duration),
            'detailed_charges' => $this->when($this->isUnlockedFor($user), $this->detailed_charges),
            'property_condition_pdf' => $this->when(
                $this->isUnlockedFor($user) && $this->hasMedia('property_condition'),
                fn () => $this->getFirstMediaUrl('property_condition')
            ),

            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'user' => $this->whenLoaded('user', function () use ($user) {
                $owner = $this->user;
                $isUnlocked = $this->isUnlockedFor($user);
                $isOwnerOrAdmin = $user?->id === $owner->id || $user?->isAdmin();

                return [
                    'id' => $owner->id,
                    'firstname' => $owner->firstname,
                    'lastname' => $owner->lastname,
                    'display_name' => $owner->fullname,
                    'avatar' => $owner->getFirstMediaUrl('avatars') ?: $this->getAvatarUrl($owner->avatar),
                    'agency_name' => $owner->agency instanceof \App\Models\Agency ? $owner->agency->name : null,
                    // Show contact info only if unlocked or owner/admin
                    'phone_number' => ($isUnlocked || $isOwnerOrAdmin) ? $owner->phone_number : null,
                    'phone_is_whatsapp' => ($isUnlocked || $isOwnerOrAdmin) ? (bool) $owner->phone_is_whatsapp : null,
                    'email' => ($isUnlocked || $isOwnerOrAdmin) ? $owner->email : null,
                ];
            }),
            'agency' => new AgencyResource($this->whenLoaded('agency')),
            'published_by' => $this->getPublisherName(),
            'quarter' => new QuarterResource($this->whenLoaded('quarter')),
            'type' => new AdTypeResource($this->whenLoaded('ad_type')),
            'images' => $this->getAccessibleImages($user)->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
                'medium' => $media->hasGeneratedConversion('medium') ? $media->getUrl('medium') : $media->getUrl(),
                'mime_type' => $media->mime_type,
                'is_primary' => $this->getMedia('images')->first()?->id === $media->id,
            ]),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }

    private function getAvatarUrl(?string $avatar): ?string
    {
        if (!$avatar) {
            return null;
        }

        if (str_starts_with($avatar, 'http')) {
            return $avatar;
        }

        if (Storage::disk('public')->exists($avatar)) {
            return Storage::disk('public')->url($avatar);
        }

        return null;
    }
}
