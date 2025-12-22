<?php

use App\Models\Ad;
use App\Models\AdType;
use App\Models\City;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('ad list endpoint is optimized and has no N+1 queries', function (): void {
    // Setup data
    $user = User::factory()->create();
    $city = City::factory()->create();
    $quarter = Quarter::factory()->create(['city_id' => $city->id]);
    $type = AdType::factory()->create();

    // Créer 20 annonces
    Ad::factory(20)->create([
        'user_id' => $user->id,
        'quarter_id' => $quarter->id,
        'type_id' => $type->id,
        'status' => 'available',
    ]);

    // Activer l'écoute DB
    DB::enableQueryLog();

    // Appeler l'API
    $response = $this->getJson('/api/v1/ads');
    $response->assertStatus(200);

    // Compter les requêtes
    $queries = DB::getQueryLog();
    $count = count($queries);

    // Dump si ça échoue pour debugger
    if ($count > 15) {
        // dump($queries);
    }

    // On s'attend à peu de requêtes :
    // 1. Count (pagination)
    // 2. Select Ads
    // 3. Eager load Quarters
    // 4. Eager load Cities
    // 5. Eager load Types
    // 6. Eager load Users
    // 7. Eager load Media
    // TOTAL ~ 7-8 requêtes, peu importe si on a 10 ou 20 annonces.
    // Si N+1 (ex: loop query media pour chaque ad), on aurait 20+7 = 27 requêtes.

    expect($count)->toBeLessThan(15);
});
