<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Zap\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Schedule */
final class AvailabilitySlotResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->schedule_type,
            'is_recurring' => $this->is_recurring,
            'frequency' => $this->frequency,
            'frequency_config' => $this->frequency_config,
            'starts_on' => $this->start_date->toDateString(),
            'ends_on' => $this->end_date?->toDateString(),
            'is_active' => $this->is_active,
            'slot_duration' => $this->metadata['slot_duration'] ?? 30,
            'buffer_minutes' => $this->metadata['buffer_minutes'] ?? 0,
            'periods' => $this->whenLoaded('periods', fn () => $this->periods->map(fn ($p): array => [
                'id' => $p->id,
                'starts_at' => $p->start_time?->format('H:i'),
                'ends_at' => $p->end_time?->format('H:i'),
            ])->toArray()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
