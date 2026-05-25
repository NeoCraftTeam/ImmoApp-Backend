<?php

declare(strict_types=1);

namespace App\Events\Reservation;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a slot becomes reserved or free again.
 * Public channel — slot availability is not sensitive data.
 * Listened by ViewingBookingPanel to update the calendar without a full page reload.
 */
final class SlotAvailabilityChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $adId,
        public readonly string $date,
        public readonly string $startsAt,
        public readonly bool $isAvailable,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel("ad.{$this->adId}.slots")];
    }

    public function broadcastAs(): string
    {
        return 'slot.availability_changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'ad_id' => $this->adId,
            'date' => $this->date,
            'starts_at' => $this->startsAt,
            'is_available' => $this->isAvailable,
        ];
    }
}
