<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Characterization net for the two duplicated blocks in UserController that are
 * about to be relocated: the inline geodetic Point-building (store + update) and
 * the avatar-media block (store + update). Behaviour is pinned at the HTTP +
 * persistence boundary so extraction onto App\Support\GeoLocation and
 * User::syncAvatarFromRequest() cannot silently change what gets stored.
 *
 * NOTE ON GEO COVERAGE: the geodetic scalar-lat/lng → Point substitution is
 * pinned as a pure value-object truth table in tests/Unit/Support/GeoLocationTest.php.
 * It cannot be exercised through update() at the HTTP layer here: the shared
 * TestCase calls Model::unguard(), so `$user->fill($data)` in update() accepts
 * the `latitude`/`longitude` keys as attributes and `save()` then targets
 * columns that do not exist on `users` (which stores geo only as the `location`
 * Point). In production the model is guarded and those keys are dropped by
 * fill(); store() sidesteps the issue entirely by mapping to `location` in an
 * explicit fill array. Update's location persistence is therefore characterized
 * here through the real `location` GeoJSON path.
 */
beforeEach(function (): void {
    Storage::fake('public');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validUserStorePayload(array $overrides = []): array
{
    $city = City::factory()->create();

    return array_merge([
        'firstname' => 'Jean',
        'lastname' => 'Dupont',
        'email' => 'jean.'.uniqid().'@example.test',
        'password' => 'motdepasse123',
        'confirm_password' => 'motdepasse123',
        'phone_number' => '+237699000000',
        'role' => 'agent',
        'type' => 'individual',
        'city_id' => $city->id,
    ], $overrides);
}

// ─── store(): geolocation ────────────────────────────────────────────────────

it('store persists a geodetic location Point from latitude/longitude', function (): void {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/v1/users', validUserStorePayload([
        'email' => 'geo-store@example.test',
        'latitude' => 3.848,
        'longitude' => 11.502,
    ]))
        ->assertCreated()
        ->assertJsonPath('message', 'Création réussie.')
        ->assertJsonStructure(['message', 'user', 'access_token']);

    $user = User::where('email', 'geo-store@example.test')->firstOrFail();

    expect($user->location)->not->toBeNull()
        ->and($user->location->getLatitude())->toEqualWithDelta(3.848, 0.0001)
        ->and($user->location->getLongitude())->toEqualWithDelta(11.502, 0.0001);
});

it('store leaves location null when no coordinates are provided', function (): void {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/v1/users', validUserStorePayload([
        'email' => 'nogeo-store@example.test',
    ]))->assertCreated();

    expect(User::where('email', 'nogeo-store@example.test')->firstOrFail()->location)->toBeNull();
});

// ─── store(): avatar ─────────────────────────────────────────────────────────

it('store attaches an uploaded avatar to the avatars collection', function (): void {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->post('/api/v1/users', validUserStorePayload([
        'email' => 'avatar-store@example.test',
        'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
    ]), ['Accept' => 'application/json'])->assertCreated();

    expect(User::where('email', 'avatar-store@example.test')->firstOrFail()->getFirstMedia('avatars'))
        ->not->toBeNull();
});

// ─── update(): geolocation via the real GeoJSON path ─────────────────────────

it('update sets the location Point from a GeoJSON location', function (): void {
    $user = User::factory()->agents()->create(['location' => null]);
    Sanctum::actingAs($user);

    $this->putJson("/api/v1/users/{$user->id}", [
        'location' => ['type' => 'Point', 'coordinates' => [9.7, 4.05]],
    ])->assertOk();

    $user->refresh();

    expect($user->location)->not->toBeNull()
        ->and($user->location->getLatitude())->toEqualWithDelta(4.05, 0.0001)
        ->and($user->location->getLongitude())->toEqualWithDelta(9.7, 0.0001);
});

it('update leaves the location untouched when no location key is sent', function (): void {
    $user = User::factory()->agents()->create([
        'location' => Point::makeGeodetic(4.05, 9.7),
    ]);
    Sanctum::actingAs($user);

    $this->putJson("/api/v1/users/{$user->id}", [
        'firstname' => 'Renamed',
    ])->assertOk();

    $user->refresh();

    expect($user->location)->not->toBeNull()
        ->and($user->location->getLatitude())->toEqualWithDelta(4.05, 0.0001)
        ->and($user->location->getLongitude())->toEqualWithDelta(9.7, 0.0001);
});

// ─── update(): avatar ────────────────────────────────────────────────────────

it('update replaces the avatar in the avatars collection', function (): void {
    $user = User::factory()->agents()->create();
    Sanctum::actingAs($user);

    $this->put("/api/v1/users/{$user->id}", [
        'avatar' => UploadedFile::fake()->image('new-avatar.png', 200, 200),
    ], ['Accept' => 'application/json'])->assertOk();

    expect($user->fresh()->getFirstMedia('avatars'))->not->toBeNull();
});
