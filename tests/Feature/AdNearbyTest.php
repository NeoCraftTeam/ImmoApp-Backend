<?php

use App\Models\Ad;
use Illuminate\Support\Facades\DB;

// POINT(lng lat) // PostGIS utilise Longitude d'abord dans POINT() mais souvent on insert via raw geometry.
// Attention : Laravel Factory UserFactory utilise "POINT($longitude $latitude)".
// AdFactory utilise Clickbar Magellan $point->toWKT().

test('can search ads nearby a location', function (): void {
    // Créer une annonce à Paris (48.8566, 2.3522)
    // On va utiliser des coordonnées brutes via DB raw pour s'assurer du format PostGIS sans dépendre de la factory complexe
    // Ou mieux : utiliser la factory si elle marche bien.

    // Annonce "Cible" (Centre Paris)
    $parisAd = Ad::factory()->create([
        'title' => 'Paris Apartment',
        'status' => 'available',
    ]);

    // Forcer la localisation SQL (plus sûr pour le test)
    // longitude 2.3522, latitude 48.8566
    DB::statement("UPDATE ad SET location = ST_SetSRID(ST_MakePoint(2.3522, 48.8566), 4326) WHERE id = '{$parisAd->id}'");

    // Annonce "Loin" (Marseille : 43.2965, 5.3698)
    $marseilleAd = Ad::factory()->create([
        'title' => 'Marseille Apartment',
        'status' => 'available',
    ]);
    DB::statement("UPDATE ad SET location = ST_SetSRID(ST_MakePoint(5.3698, 43.2965), 4326) WHERE id = '{$marseilleAd->id}'");

    // Recherche autour de Paris (Rayon 10km)
    $response = $this->getJson('/api/v1/ads/nearby?latitude=48.8566&longitude=2.3522&radius=10000');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data') // Seulement Paris
        ->assertJsonPath('data.0.id', $parisAd->id);

    // Recherche large (Rayon 1000km) -> Doit être cappé à 50km
    // Donc on ne trouve que Paris (0km), Marseille (700km) est exclu.
    $responseBig = $this->getJson('/api/v1/ads/nearby?latitude=48.8566&longitude=2.3522&radius=1000000');

    $responseBig->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $parisAd->id);
});

test('nearby search requires coordinates', function (): void {
    $response = $this->getJson('/api/v1/ads/nearby');
    $response->assertStatus(422); // Validation error
});
