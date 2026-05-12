<?php

use App\Models\Ad;
use App\Models\AdType;
use App\Models\City;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;

uses(RefreshDatabase::class);

test('ad list endpoint is optimized and has no N+1 queries', function (): void {
    config([
        'telescope.enabled' => false,
        'pulse.enabled' => false,
    ]);
    Telescope::stopRecording();

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

    // Activer l'écoute DB (vider le journal pour ne pas cumuler les requêtes des tests précédents)
    DB::enableQueryLog();
    DB::flushQueryLog();

    // Appeler l'API
    $response = $this->getJson('/api/v1/ads');
    $response->assertStatus(200);

    // Compter les requêtes
    $queries = DB::getQueryLog();
    $count = count($queries);

    // Baseline attendu (sans N+1 sur les annonces) : pagination + eager loads + cache/settings.
    // Un N+1 classique (ex. une requête média par annonce) ferait grimper le total avec la page (15+).
    expect($count)->toBeLessThan(40);
});
