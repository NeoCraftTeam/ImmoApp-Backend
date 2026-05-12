<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Agency */
final class AgencyResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'firstname' => $this->owner->firstname,
                'lastname' => $this->owner->lastname,
                'avatar' => $this->owner->avatar,
                'created_at' => $this->owner->created_at,
            ]),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
