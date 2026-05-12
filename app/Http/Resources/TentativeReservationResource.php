<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TentativeReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TentativeReservation */
final class TentativeReservationResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'slot_date' => $this->slot_date->toDateString(),
            'slot_starts_at' => $this->slot_starts_at,
            'slot_ends_at' => $this->slot_ends_at,
            'client_message' => $this->client_message,
            'landlord_notes' => $this->landlord_notes,
            'cancelled_by' => $this->cancelled_by?->value,
            'cancellation_reason' => $this->cancellation_reason,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'ad' => new AdResource($this->whenLoaded('ad')),
            'client' => $this->when(
                $this->relationLoaded('client'),
                function () use ($request) {
                    $forLandlord = $this->clientContactVisibleToLandlord($request);
                    if ($forLandlord !== null) {
                        return $forLandlord;
                    }

                    return new UserResource($this->client);
                }
            ),

            'next_steps' => $this->when(
                $this->wasRecentlyCreated,
                'Votre créneau est retenu pendant 24h. Le propriétaire vous contactera pour confirmer la visite. Assurez-vous d\'être joignable sur votre numéro enregistré.'
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function clientContactVisibleToLandlord(Request $request): ?array
    {
        if (!$this->relationLoaded('ad') || !$this->relationLoaded('client')) {
            return null;
        }

        if ($request->user()?->id !== $this->ad->user_id) {
            return null;
        }

        $client = $this->client;

        return [
            'id' => $client->id,
            'firstname' => $client->firstname,
            'lastname' => $client->lastname,
            'avatar' => $client->getFilamentAvatarUrl(),
            'phone_number' => $client->phone_number,
            'email' => $client->email,
        ];
    }
}
