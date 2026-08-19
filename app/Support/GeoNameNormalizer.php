<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class GeoNameNormalizer
{
    public static function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->toString();
    }
}
