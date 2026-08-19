<?php

declare(strict_types=1);

use App\Support\GeoNameNormalizer;

it('normalizes accents punctuation and compound place names for search', function (): void {
    expect(GeoNameNormalizer::normalize("  N'Djaména — Centre  "))
        ->toBe('n djamena centre');
});

it('does not alter the original display name', function (): void {
    $name = 'La Chaux-de-Fonds';

    GeoNameNormalizer::normalize($name);

    expect($name)->toBe('La Chaux-de-Fonds');
});
