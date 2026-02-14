<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        $isAgency = $this->type === \App\Enums\UserType::AGENCY;
        $agencyName = $this->agency instanceof \App\Models\Agency ? $this->agency->name : null;

        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'avatar' => $this->getFirstMediaUrl('avatar') ?: $this->avatar,
            'display_name' => $this->fullname,
            'name' => $this->fullname,
            'agency_name' => ($this->agency instanceof \App\Models\Agency) ? $this->agency->name : null,

            // Le propriétaire du compte ou un admin peut voir le role/type
            'role' => $this->when($request->user()?->id === $this->id || $request->user()?->isAdmin(), $this->role),
            'type' => $this->when($request->user()?->id === $this->id || $request->user()?->isAdmin(), $this->type),

            'created_at' => $this->when($request->user()?->isAdmin(), $this->created_at),
            'updated_at' => $this->when($request->user()?->isAdmin(), $this->updated_at),
            'city_id' => $this->city_id,
            'city_name' => $this->city?->name,
        ];
    }
}
