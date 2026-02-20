<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'phone_number' => $this->when(
                $request->user()?->id === $this->id || $request->user()?->isAdmin(),
                $this->phone_number
            ),
            'email' => $this->when(
                $request->user()?->id === $this->id || $request->user()?->isAdmin(),
                $this->email
            ),
            'avatar' => $this->getFirstMediaUrl('avatars') ?: $this->getAvatarUrl(),
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

    private function getAvatarUrl(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        if (Storage::disk('public')->exists($this->avatar)) {
            return Storage::disk('public')->url($this->avatar);
        }

        return null;
    }
}
