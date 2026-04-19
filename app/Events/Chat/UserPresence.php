<?php

declare(strict_types=1);

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Presence channel event for online/offline status across the platform.
 */
final class UserPresence implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly string $name,
        public readonly ?string $avatar,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('online-users')];
    }

    public function broadcastAs(): string
    {
        return 'user.presence';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'     => $this->userId,
            'name'   => $this->name,
            'avatar' => $this->avatar,
        ];
    }
}
