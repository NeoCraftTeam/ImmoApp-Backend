<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\DisputeMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DisputeMessage
 */
class DisputeMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dispute_id' => $this->dispute_id,
            'sender_id' => $this->sender_id,
            'sender' => $this->whenLoaded('sender', fn () => [
                'id' => $this->sender->id,
                'name' => $this->sender->fullname,
                'is_admin' => ($this->sender->role ?? null) === UserRole::ADMIN,
            ]),
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
