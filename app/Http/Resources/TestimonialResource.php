<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A privacy-safe representation of a review for the public landing page.
 * Exposes only first name + last initial — no email, phone, or ID.
 *
 * @mixin Review
 */
final class TestimonialResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $user = $this->resource->user;

        $firstname = $user instanceof User ? $user->firstname : '';
        $lastname = $user instanceof User ? $user->lastname : '';

        /** Anonymised display name: "Aliou D." */
        $displayName = trim($firstname.($lastname ? ' '.mb_strtoupper(mb_substr($lastname, 0, 1)).'.' : ''));

        /** Avatar initials: up to 2 chars */
        $initials = mb_strtoupper(
            mb_substr($firstname, 0, 1).mb_substr($lastname, 0, 1)
        ) ?: '?';

        $roleLabel = $user instanceof User ? $user->role->getLabel() : 'Utilisateur';
        $cityName = $user instanceof User ? $user->city?->name : null;

        $role = $cityName !== null && $cityName !== ''
            ? "{$roleLabel} · {$cityName}"
            : $roleLabel;

        return [
            'id' => $this->id,
            'display_name' => $displayName ?: 'Utilisateur',
            'initials' => $initials,
            'role' => $role,
            'rating' => (float) $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->translatedFormat('F Y'),
        ];
    }
}
