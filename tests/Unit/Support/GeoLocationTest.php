<?php

declare(strict_types=1);

use App\Support\GeoLocation;
use Clickbar\Magellan\Data\Geometries\Point;

/**
 * Characterization net for the geodetic-Point substitution about to land in
 * UserController::store() and ::update(). Both sites currently build the Point
 * inline:
 *
 *     isset($data['latitude'], $data['longitude'])
 *         ? Point::makeGeodetic((float) $data['latitude'], (float) $data['longitude'])
 *         : null
 *
 * and update() additionally resets to null when either coordinate is null. The
 * refactor replaces that with `GeoLocation::fromArray($data)?->toPoint()`, so
 * these cases lock the exact truth table of that expression: a Point iff BOTH
 * coordinates are present and non-null, otherwise null. If the extraction ever
 * diverged (wrong factory, dropped guard, int/float drift) one of these breaks.
 */

// ─── fromArray(): the isset() truth table ────────────────────────────────────

it('builds a value object when both coordinates are present', function (): void {
    $geo = GeoLocation::fromArray(['latitude' => 3.848, 'longitude' => 11.502]);

    expect($geo)->toBeInstanceOf(GeoLocation::class)
        ->and($geo->latitude)->toBe(3.848)
        ->and($geo->longitude)->toBe(11.502)
        ->and($geo->radius)->toBeNull();
});

it('casts numeric-string coordinates to float', function (): void {
    $geo = GeoLocation::fromArray(['latitude' => '4.05', 'longitude' => '9.7']);

    expect($geo?->latitude)->toBe(4.05)
        ->and($geo?->longitude)->toBe(9.7);
});

it('returns null when a coordinate is missing or null', function (array $data): void {
    expect(GeoLocation::fromArray($data))->toBeNull();
})->with([
    'both absent' => [[]],
    'only latitude' => [['latitude' => 3.848]],
    'only longitude' => [['longitude' => 11.502]],
    'latitude null' => [['latitude' => null, 'longitude' => 11.502]],
    'longitude null' => [['latitude' => 3.848, 'longitude' => null]],
    'both null' => [['latitude' => null, 'longitude' => null]],
]);

// ─── toPoint(): equivalence with the inline Point::makeGeodetic() call ────────

it('produces a geodetic Point equivalent to the inline construction', function (): void {
    $data = ['latitude' => 3.848, 'longitude' => 11.502];

    $extracted = GeoLocation::fromArray($data)?->toPoint();
    $inline = Point::makeGeodetic($data['latitude'], $data['longitude']);

    expect($extracted)->toBeInstanceOf(Point::class)
        ->and($extracted?->getLatitude())->toBe($inline->getLatitude())
        ->and($extracted?->getLongitude())->toBe($inline->getLongitude())
        ->and($extracted?->getSrid())->toBe($inline->getSrid());
});

it('short-circuits to null through the nullsafe operator when coordinates are absent', function (): void {
    expect(GeoLocation::fromArray([])?->toPoint())->toBeNull();
});
