<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\PropertyAttribute;
use App\Enums\TransactionType;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\City;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes meublé-by-type ads when filtering by furnished attribute', function (): void {
    $city = City::factory()->create(['name' => 'Douala']);
    $quarter = Quarter::factory()->create(['city_id' => $city->id]);
    $typeMeuble = AdType::factory()->create(['name' => 'appartement meublé']);
    $owner = User::factory()->create();

    $adMeubleByTypeOnly = Ad::factory()->create([
        'user_id' => $owner->id,
        'quarter_id' => $quarter->id,
        'type_id' => $typeMeuble->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'transaction_type' => TransactionType::LOCATION,
        'attributes' => [],
        'title' => 'Bel appartement meublé Douala',
    ]);

    $response = $this->getJson('/api/v1/ads/search?'.http_build_query([
        'city' => 'Douala',
        'attributes' => [PropertyAttribute::Furnished->value],
    ]));

    $response->assertSuccessful();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($adMeubleByTypeOnly->id);
});
