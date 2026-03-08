<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 *
 * @OA\Schema(
 *     schema="UserResource",
 *
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="firstname", type="string"),
 *     @OA\Property(property="lastname", type="string"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="phone_number", type="string", nullable=true),
 *     @OA\Property(property="role", type="string"),
 *     @OA\Property(property="type", type="string", nullable=true),
 *     @OA\Property(property="avatar", type="string", nullable=true),
 *     @OA\Property(property="is_verified", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
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
            'agency_name' => $this->whenLoaded('agency', fn () => $this->agency instanceof \App\Models\Agency ? $this->agency->name : null),

            // Le propriétaire du compte ou un admin peut voir le role/type
            'role' => $this->when($request->user()?->id === $this->id || $request->user()?->isAdmin(), $this->role),
            'type' => $this->when($request->user()?->id === $this->id || $request->user()?->isAdmin(), $this->type),

            'created_at' => $this->when($request->user()?->isAdmin(), $this->created_at),
            'updated_at' => $this->when($request->user()?->isAdmin(), $this->updated_at),
            'city_id' => $this->city_id,
            'city_name' => $this->whenLoaded('city', fn () => $this->city->name),
            'point_balance' => $this->when(
                $request->user()?->id === $this->id,
                (int) $this->point_balance
            ),
            'onboarding_completed_at' => $this->when(
                $request->user()?->id === $this->id,
                $this->onboarding_completed_at,
            ),
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
