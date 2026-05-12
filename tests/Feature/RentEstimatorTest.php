<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\TransactionType;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\City;
use App\Models\Quarter;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['features.rent_estimator' => true]);
});

function makeRentalAd(
    string $quarterId,
    string $typeId,
    float $price,
    float $surface,
    TransactionType|string|null $transactionType = TransactionType::LOCATION,
    AdStatus $status = AdStatus::AVAILABLE,
    bool $visible = true,
): Ad {
    $userId = User::factory()->agents()->create()->id;

    return Ad::factory()->create([
        'user_id' => $userId,
        'quarter_id' => $quarterId,
        'type_id' => $typeId,
        'price' => $price,
        'surface_area' => $surface,
        'transaction_type' => $transactionType,
        'status' => $status,
        'is_visible' => $visible,
        'location' => Point::makeGeodetic(4.0511, 9.7679),
    ]);
}

it('estimates rent from location ads only and ignores vente', function (): void {
    $city = City::factory()->create(['name' => 'Douala Test']);
    $quarter = Quarter::factory()->create(['city_id' => $city->id]);
    $type = AdType::factory()->create(['name' => 'studio test']);

    makeRentalAd($quarter->id, $type->id, 150_000, 50.0);
    makeRentalAd($quarter->id, $type->id, 180_000, 50.0);
    makeRentalAd($quarter->id, $type->id, 120_000, 50.0);

    makeRentalAd($quarter->id, $type->id, 50_000_000, 50.0, TransactionType::VENTE);

    $response = $this->getJson('/api/v1/rent-estimate?'.http_build_query([
        'city_id' => $city->id,
        'type_id' => $type->id,
        'surface' => 50,
    ]));

    $response->assertSuccessful();
    $data = $response->json();

    expect($data)->not->toHaveKey('error')
        ->and($data['type_scope_matched'])->toBeTrue()
        ->and($data['sample_count'])->toBe(3)
        ->and($data['estimated_median'])->toBe(150_000);
});

it('falls back to city when the requested type has no rentals and reports scope', function (): void {
    $city = City::factory()->create(['name' => 'Yaounde Est']);
    $quarter = Quarter::factory()->create(['city_id' => $city->id]);
    $typeEmpty = AdType::factory()->create(['name' => 'chambre est']);
    $typeWithData = AdType::factory()->create(['name' => 'studio est']);

    makeRentalAd($quarter->id, $typeWithData->id, 100_000, 40.0);
    makeRentalAd($quarter->id, $typeWithData->id, 100_000, 40.0);
    makeRentalAd($quarter->id, $typeWithData->id, 100_000, 40.0);

    $response = $this->getJson('/api/v1/rent-estimate?'.http_build_query([
        'city_id' => $city->id,
        'type_id' => $typeEmpty->id,
        'surface' => 40,
    ]));

    $response->assertSuccessful();
    $data = $response->json();

    expect($data)->not->toHaveKey('error')
        ->and($data['type_scope_matched'])->toBeFalse()
        ->and($data['sample_count'])->toBe(3)
        ->and($data['estimated_median'])->toBe(100_000);
});

it('excludes non visible ads', function (): void {
    $city = City::factory()->create(['name' => 'Garoua Est']);
    $quarter = Quarter::factory()->create(['city_id' => $city->id]);
    $type = AdType::factory()->create(['name' => 'maison est']);

    makeRentalAd($quarter->id, $type->id, 300_000, 100.0);
    makeRentalAd($quarter->id, $type->id, 300_000, 100.0);
    makeRentalAd($quarter->id, $type->id, 300_000, 100.0);
    makeRentalAd($quarter->id, $type->id, 50_000, 10.0, TransactionType::LOCATION, AdStatus::AVAILABLE, false);

    $response = $this->getJson('/api/v1/rent-estimate?'.http_build_query([
        'city_id' => $city->id,
        'type_id' => $type->id,
        'surface' => 100,
    ]));

    $response->assertSuccessful();
    expect($response->json('sample_count'))->toBe(3);
});

it('returns error when there is no usable data', function (): void {
    $city = City::factory()->create();
    $quarter = Quarter::factory()->create(['city_id' => $city->id]);
    $type = AdType::factory()->create();

    makeRentalAd($quarter->id, $type->id, 20_000_000, 100.0, TransactionType::VENTE);

    $response = $this->getJson('/api/v1/rent-estimate?'.http_build_query([
        'city_id' => $city->id,
        'type_id' => $type->id,
        'surface' => 80,
    ]));

    $response->assertSuccessful();
    expect($response->json('error'))->toBe('Pas assez de données pour cette ville.');
});
