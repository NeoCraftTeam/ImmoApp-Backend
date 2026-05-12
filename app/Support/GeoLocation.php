<?php

declare(strict_types=1);

namespace App\Support;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\Request;

class GeoLocation
{
    public const float MAX_RADIUS = 50_000;

    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $radius = null,
    ) {}

    /**
     * Build from request lat/lng/radius inputs.
     * Returns null if latitude or longitude are missing or invalid.
     */
    public static function fromRequest(Request $request): ?self
    {
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');

        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        $latitude = (float) $lat;
        $longitude = (float) $lng;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        $radius = is_numeric($request->input('radius'))
            ? min((float) $request->input('radius'), self::MAX_RADIUS)
            : null;

        return new self($latitude, $longitude, $radius);
    }

    /**
     * Build from an associative array with 'latitude' and 'longitude' keys.
     */
    public static function fromArray(array $data): ?self
    {
        if (!isset($data['latitude'], $data['longitude'])) {
            return null;
        }

        return new self((float) $data['latitude'], (float) $data['longitude']);
    }

    public function toPoint(): Point
    {
        return Point::makeGeodetic($this->latitude, $this->longitude);
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    public function withRadius(float $radius): self
    {
        return new self($this->latitude, $this->longitude, min($radius, self::MAX_RADIUS));
    }
}
