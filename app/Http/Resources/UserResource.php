<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'avatar' => $this->getFirstMediaUrl('avatar') ?: $this->avatar,

            // Champ sensible visible seulement par un admin
            'role' => $this->when($request->user()?->isAdmin(), $this->role),
            'type' => $this->when($request->user()?->isAdmin(), $this->type),

            'created_at' => $this->when($request->user()?->isAdmin(), $this->created_at),
            'updated_at' => $this->when($request->user()?->isAdmin(), $this->updated_at),
            'city_id' => $this->city_id,
            'city_name' => $this->city->name,
        ];
    }
}
