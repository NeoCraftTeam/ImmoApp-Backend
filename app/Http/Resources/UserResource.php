<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Agency;
use App\Models\City;
use App\Models\TrustScore;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
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
            'username' => $this->username,
            'bio' => $this->bio,
            'phone_number' => $this->when(
                $request->user()?->id === $this->id || $request->user()?->isAdmin(),
                $this->phone_number
            ),
            'phone_is_whatsapp' => $this->when(
                $request->user()?->id === $this->id || $request->user()?->isAdmin(),
                (bool) $this->phone_is_whatsapp
            ),
            'email' => $this->when(
                $request->user()?->id === $this->id || $request->user()?->isAdmin(),
                $this->email
            ),
            'avatar' => $this->getFirstMediaUrl('avatars') ?: $this->getAvatarUrl(),
            'display_name' => $this->fullname,
            'name' => $this->fullname,
            'agency_name' => $this->whenLoaded('agency', fn () => $this->agency instanceof Agency ? $this->agency->name : null),

            // Le propriétaire du compte ou un admin peut voir le role/type
            'role' => $this->when($request->user()?->id === $this->id || $request->user()?->isAdmin(), $this->role),
            'type' => $this->when($request->user()?->id === $this->id || $request->user()?->isAdmin(), $this->type),

            'created_at' => $this->when($request->user()?->isAdmin(), $this->created_at),
            'updated_at' => $this->when($request->user()?->isAdmin(), $this->updated_at),
            'city_id' => $this->city_id,
            'city_name' => $this->when(
                !empty($this->city_id),
                fn () => $this->relationLoaded('city')
                    ? $this->city?->name
                    : Cache::remember("city:name:{$this->city_id}", now()->addHours(1), fn () => City::find($this->city_id)?->name)
            ),
            'point_balance' => $this->when(
                $request->user()?->id === $this->id,
                (int) $this->point_balance
            ),
            // Providers OAuth liés au compte (pour l'écran Sécurité mobile).
            'linked_providers' => $this->when(
                $request->user()?->id === $this->id,
                fn () => collect(['google', 'facebook', 'apple', 'github'])
                    ->filter(fn (string $p) => !empty($this->{$p.'_id'}))
                    ->values()
                    ->all(),
            ),
            'has_password' => $this->when(
                $request->user()?->id === $this->id,
                fn () => !empty($this->password),
            ),
            'onboarding_completed_at' => $this->when(
                $request->user()?->id === $this->id,
                $this->onboarding_completed_at,
            ),
            'last_home_visit_at' => $this->when(
                $request->user()?->id === $this->id,
                $this->last_home_visit_at,
            ),
            'preferences' => $this->when(
                $request->user()?->id === $this->id,
                $this->preferences ?? [],
            ),
            'chat_e2ee_public_key_pem' => $this->when(
                $request->user()?->id === $this->id,
                $this->chat_e2ee_public_key_pem,
            ),

            // Item 6 — Trust badge (always public)
            'is_verified' => $this->email_verified_at !== null,
            'trust_score' => $this->getTrustScoreValue(),
            'trust_tier' => $this->getTrustTierValue(),
            'trust_tier_label' => $this->getTrustTierLabel(),
            'trust_tier_hex_color' => $this->getTrustTierHexColor(),
        ];
    }

    private function latestTrustScore(): ?TrustScore
    {
        return Cache::remember(
            "trust_score_resource:{$this->id}",
            now()->addHour(),
            fn () => $this->trustScores()->latest('computed_at')->first(),
        );
    }

    private function getTrustScoreValue(): int
    {
        $ts = $this->latestTrustScore();

        return $ts !== null ? $ts->score : 0;
    }

    private function getTrustTierValue(): string
    {
        $ts = $this->latestTrustScore();

        return $ts !== null ? $ts->tier->value : 'non_verifie';
    }

    private function getTrustTierLabel(): string
    {
        $ts = $this->latestTrustScore();

        return $ts !== null ? $ts->tier->label() : 'Non vérifié';
    }

    private function getTrustTierHexColor(): string
    {
        $ts = $this->latestTrustScore();

        return $ts !== null ? $ts->tier->hexColor() : '#9CA3AF';
    }

    private function getAvatarUrl(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        $disk = config('filesystems.app_media_disk');
        if (Storage::disk($disk)->exists($this->avatar)) {
            return Storage::disk($disk)->url($this->avatar);
        }

        return null;
    }
}
