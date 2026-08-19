<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin City */
final class CityResource extends JsonResource
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
            'country' => $this->country,
            'country_code' => $this->country_code,
            'admin_area' => $this->admin_area,
            'place_type' => $this->place_type,
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }
}
