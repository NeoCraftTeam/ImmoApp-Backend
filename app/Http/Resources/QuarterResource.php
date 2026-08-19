<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quarter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Quarter */
final class QuarterResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {

        $lat = $this->location?->getY() ?? ($this->latitude !== null ? (float) $this->latitude : null);
        $lng = $this->location?->getX() ?? ($this->longitude !== null ? (float) $this->longitude : null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name ?? $this->name,
            'city_id' => $this->city_id,
            'city_name' => $this->city->name,
            'place_type' => $this->place_type,
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }
}
