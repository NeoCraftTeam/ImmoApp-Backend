<?php

declare(strict_types=1);

namespace App\Events\Reservation;

use App\Models\TentativeReservation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a reservation status changes (pending→confirmed, cancelled, expired, completed, no_show).
 * Sent on private channels of both parties so each gets real-time UI updates.
 */
final class ReservationStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TentativeReservation $reservation,
        public readonly string $previousStatus,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("user.{$this->reservation->client_id}"),
        ];

        $landlordId = $this->reservation->ad->user_id;
        if ($landlordId && $landlordId !== $this->reservation->client_id) {
            $channels[] = new PrivateChannel("user.{$landlordId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'reservation.status_changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'status' => $this->reservation->status->value,
            'status_label' => $this->reservation->status->label(),
            'previous_status' => $this->previousStatus,
            'ad_id' => $this->reservation->ad_id,
            'slot_date' => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
            'slot_ends_at' => $this->reservation->slot_ends_at,
        ];
    }
}
