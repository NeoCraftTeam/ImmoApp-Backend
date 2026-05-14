# 🏠 SYSTÈME DE VISITE VIRTUELLE 3D — Documentation Complète
## KeyHome · Laravel + Filament · Next.js · Flutter (à venir)

> **Stack :** Laravel 11 · Filament PHP 3 · Next.js 14 · Pannellum.js · S3/Cloudflare R2
> **Principe :** Le bailleur uploade ses photos 360° depuis son panel Filament → le client
> visualise le tour 3D directement sur la page de l'annonce, sans aucun outil tiers payant.

---

## 📋 TABLE DES MATIÈRES

1. [Architecture globale](#1-architecture-globale)
2. [Base de données — Migration](#2-base-de-données--migration)
3. [Backend Laravel — Modèles & Services](#3-backend-laravel--modèles--services)
4. [API Endpoints](#4-api-endpoints)
5. [Panel Filament — Interface Bailleur](#5-panel-filament--interface-bailleur)
6. [Frontend Next.js — Éditeur Hotspots](#6-frontend-nextjs--éditeur-hotspots)
7. [Frontend Next.js — Viewer Client](#7-frontend-nextjs--viewer-client)
8. [Intégration dans AdDetails.tsx](#8-intégration-dans-addetailstsx)
9. [Sécurité & Validation](#9-sécurité--validation)
10. [Flutter — Préparation mobile](#10-flutter--préparation-mobile)
11. [Checklist déploiement](#11-checklist-déploiement)

---

## 1. ARCHITECTURE GLOBALE

```
BAILLEUR (Panel Filament)
   │
   │  1. Lit les instructions (guide par téléphone)
   │  2. Uploade ses photos 360° (1 par pièce)
   │  3. Nomme chaque pièce
   │  4. Place des hotspots visuellement (clic sur la photo)
   │  5. Publie le tour
   │
   ▼
LARAVEL BACKEND
   │  - Valide chaque photo (MIME réel, taille, format equirectangulaire)
   │  - Stocke sur S3 / Cloudflare R2
   │  - Sauvegarde le JSON de config en BDD
   │  - Retourne la config au frontend
   │
   ▼
NEXT.JS FRONTEND (Client)
   │  - Reçoit la config JSON depuis Laravel
   │  - Affiche un bouton "🔴 Visite en direct 3D" sur AdDetails
   │  - Ouvre un modal fullscreen avec Pannellum
   │  - Navigation entre pièces via hotspots
   │
   ▼
FLUTTER (à venir — Phase 2)
   - Même API Laravel
   - WebView ou package dédié
```

---

## 2. BASE DE DONNÉES — MIGRATION

```php
<?php
// database/migrations/xxxx_add_3d_tour_to_properties_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->boolean('has_3d_tour')->default(false)->after('description');
            $table->json('tour_config')->nullable()->after('has_3d_tour');
            // Structure du JSON :
            // {
            //   "default_scene": "scene_salon",
            //   "scenes": [
            //     {
            //       "id": "scene_salon",
            //       "title": "Salon",
            //       "image_url": "https://cdn.keyhome.app/tours/1/salon.jpg",
            //       "initial_view": { "pitch": 0, "yaw": 0, "hfov": 110 },
            //       "hotspots": [
            //         {
            //           "pitch": -10.5,
            //           "yaw": 45.2,
            //           "target_scene": "scene_chambre",
            //           "label": "Aller à la chambre",
            //           "type": "scene"
            //         }
            //       ]
            //     }
            //   ]
            // }
            $table->timestamp('tour_published_at')->nullable()->after('tour_config');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['has_3d_tour', 'tour_config', 'tour_published_at']);
        });
    }
};
```

---

## 3. BACKEND LARAVEL — MODÈLES & SERVICES

### 3.1 — Modèle `Property` (ajouts)

```php
<?php
// app/Models/Property.php — ajouter ces éléments

protected $casts = [
    // ... casts existants ...
    'has_3d_tour'        => 'boolean',
    'tour_config'        => 'array',
    'tour_published_at'  => 'datetime',
];

protected $appends = [
    // ... appends existants ...
    'tour_scenes_count',
];

// Accessor — nombre de scènes dans le tour
public function getTourScenesCountAttribute(): int
{
    if (!$this->tour_config) return 0;
    return count($this->tour_config['scenes'] ?? []);
}

// Scope — propriétés avec tour 3D
public function scopeWithTour(Builder $query): Builder
{
    return $query->where('has_3d_tour', true)->whereNotNull('tour_config');
}
```

### 3.2 — Service `TourService`

```php
<?php
// app/Services/TourService.php

namespace App\Services;

use App\Models\Property;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TourService
{
    /**
     * Uploader une scène (photo 360°) sur S3 et retourner son URL publique.
     */
    public function uploadScene(Property $property, UploadedFile $file, string $sceneTitle): array
    {
        // Générer un nom de fichier sécurisé
        $slug     = Str::slug($sceneTitle) ?: 'scene-' . Str::random(6);
        $filename = "{$slug}-" . time() . '.' . $file->getClientOriginalExtension();
        $path     = "tours/{$property->id}/{$filename}";

        // Vérification MIME réelle (pas uniquement l'extension)
        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/webp'])) {
            throw new \InvalidArgumentException("Format non supporté : {$mime}. Utilise JPG ou WEBP.");
        }

        // Upload sur S3 / Cloudflare R2
        Storage::disk('r2')->put($path, file_get_contents($file));
        $url = Storage::disk('r2')->url($path);

        return [
            'id'          => 'scene_' . Str::slug($sceneTitle) . '_' . Str::random(4),
            'title'       => $sceneTitle,
            'image_url'   => $url,
            'initial_view'=> ['pitch' => 0, 'yaw' => 0, 'hfov' => 110],
            'hotspots'    => [],
        ];
    }

    /**
     * Sauvegarder la configuration complète du tour (scènes + hotspots).
     */
    public function saveTourConfig(Property $property, array $scenes): void
    {
        $config = [
            'default_scene' => $scenes[0]['id'] ?? null,
            'scenes'        => $scenes,
        ];

        $property->update([
            'has_3d_tour'       => true,
            'tour_config'       => $config,
            'tour_published_at' => now(),
        ]);
    }

    /**
     * Supprimer le tour et toutes ses images S3.
     */
    public function deleteTour(Property $property): void
    {
        if (!$property->tour_config) return;

        foreach ($property->tour_config['scenes'] ?? [] as $scene) {
            $path = parse_url($scene['image_url'], PHP_URL_PATH);
            Storage::disk('r2')->delete(ltrim($path, '/'));
        }

        $property->update([
            'has_3d_tour'       => false,
            'tour_config'       => null,
            'tour_published_at' => null,
        ]);
    }

    /**
     * Mettre à jour uniquement les hotspots d'une scène existante.
     * (Appelé depuis le frontend éditeur)
     */
    public function updateHotspots(Property $property, string $sceneId, array $hotspots): void
    {
        $config = $property->tour_config;

        $config['scenes'] = array_map(function ($scene) use ($sceneId, $hotspots) {
            if ($scene['id'] === $sceneId) {
                $scene['hotspots'] = $hotspots;
            }
            return $scene;
        }, $config['scenes']);

        $property->update(['tour_config' => $config]);
    }
}
```

---

## 4. API ENDPOINTS

### 4.1 — Routes

```php
<?php
// routes/api.php — ajouter dans le groupe auth:sanctum

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ── Tour 3D ──────────────────────────────────────────────────────────────

    // Récupérer la config du tour (accessible aussi aux visiteurs non connectés)
    Route::get('/properties/{property}/tour', [TourController::class, 'show'])
        ->withoutMiddleware(['auth:sanctum']);

    // Upload des scènes (bailleur uniquement)
    Route::post('/properties/{property}/tour/scenes', [TourController::class, 'uploadScenes'])
        ->middleware('throttle:10,1');

    // Mettre à jour les hotspots d'une scène
    Route::patch('/properties/{property}/tour/scenes/{sceneId}/hotspots', [TourController::class, 'updateHotspots']);

    // Supprimer le tour complet
    Route::delete('/properties/{property}/tour', [TourController::class, 'destroy']);
});
```

### 4.2 — Controller `TourController`

```php
<?php
// app/Http/Controllers/Api/TourController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\TourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    public function __construct(private TourService $tourService) {}

    // ─── GET /api/v1/properties/{property}/tour ───────────────────────────────
    public function show(Property $property): JsonResponse
    {
        if (!$property->has_3d_tour || !$property->tour_config) {
            return response()->json(['message' => 'Aucun tour 3D pour cette propriété'], 404);
        }

        return response()->json([
            'has_tour'           => true,
            'scenes_count'       => $property->tour_scenes_count,
            'tour_published_at'  => $property->tour_published_at,
            'config'             => $property->tour_config,
        ]);
    }

    // ─── POST /api/v1/properties/{property}/tour/scenes ──────────────────────
    public function uploadScenes(Request $request, Property $property): JsonResponse
    {
        $this->authorize('update', $property);

        $request->validate([
            'scenes'              => ['required', 'array', 'min:1', 'max:20'],
            'scenes.*.title'      => ['required', 'string', 'max:50'],
            'scenes.*.image'      => ['required', 'file', 'mimes:jpg,jpeg,webp', 'max:30720'], // 30MB
            'scenes.*.hotspots'   => ['nullable', 'array'],
            'scenes.*.hotspots.*.pitch'        => ['required_with:scenes.*.hotspots', 'numeric', 'between:-90,90'],
            'scenes.*.hotspots.*.yaw'          => ['required_with:scenes.*.hotspots', 'numeric', 'between:-180,180'],
            'scenes.*.hotspots.*.target_scene' => ['required_with:scenes.*.hotspots', 'string'],
            'scenes.*.hotspots.*.label'        => ['required_with:scenes.*.hotspots', 'string', 'max:40'],
        ]);

        $uploadedScenes = [];

        foreach ($request->file('scenes') as $i => $sceneData) {
            $scene = $this->tourService->uploadScene(
                $property,
                $sceneData['image'],
                $request->input("scenes.{$i}.title")
            );
            $scene['hotspots'] = $request->input("scenes.{$i}.hotspots", []);
            $uploadedScenes[]  = $scene;
        }

        $this->tourService->saveTourConfig($property, $uploadedScenes);

        return response()->json([
            'message'      => 'Tour 3D publié avec succès !',
            'scenes_count' => count($uploadedScenes),
            'config'       => $property->fresh()->tour_config,
        ], 201);
    }

    // ─── PATCH /api/v1/properties/{property}/tour/scenes/{sceneId}/hotspots ──
    public function updateHotspots(Request $request, Property $property, string $sceneId): JsonResponse
    {
        $this->authorize('update', $property);

        $request->validate([
            'hotspots'                => ['required', 'array'],
            'hotspots.*.pitch'        => ['required', 'numeric', 'between:-90,90'],
            'hotspots.*.yaw'          => ['required', 'numeric', 'between:-180,180'],
            'hotspots.*.target_scene' => ['required', 'string'],
            'hotspots.*.label'        => ['required', 'string', 'max:40'],
        ]);

        $this->tourService->updateHotspots($property, $sceneId, $request->hotspots);

        return response()->json(['message' => 'Hotspots mis à jour']);
    }

    // ─── DELETE /api/v1/properties/{property}/tour ────────────────────────────
    public function destroy(Property $property): JsonResponse
    {
        $this->authorize('update', $property);
        $this->tourService->deleteTour($property);

        return response()->json(['message' => 'Tour 3D supprimé']);
    }
}
```

### 4.3 — Ressource API (format de réponse)

```json
// GET /api/v1/properties/{id}/tour — Réponse exemple
{
  "has_tour": true,
  "scenes_count": 3,
  "tour_published_at": "2025-03-08T14:30:00Z",
  "config": {
    "default_scene": "scene_salon_abc1",
    "scenes": [
      {
        "id": "scene_salon_abc1",
        "title": "Salon",
        "image_url": "https://cdn.keyhome.app/tours/42/salon-1741440600.jpg",
        "initial_view": { "pitch": 0, "yaw": 0, "hfov": 110 },
        "hotspots": [
          {
            "pitch": -8.5,
            "yaw": 120.3,
            "target_scene": "scene_chambre-principale_def2",
            "label": "Chambre principale",
            "type": "scene"
          }
        ]
      },
      {
        "id": "scene_chambre-principale_def2",
        "title": "Chambre principale",
        "image_url": "https://cdn.keyhome.app/tours/42/chambre-1741440601.jpg",
        "initial_view": { "pitch": 0, "yaw": 180, "hfov": 110 },
        "hotspots": [
          {
            "pitch": -5.0,
            "yaw": -60.0,
            "target_scene": "scene_salon_abc1",
            "label": "Retour au salon",
            "type": "scene"
          }
        ]
      }
    ]
  }
}
```

---

## 5. PANEL FILAMENT — INTERFACE BAILLEUR

### 5.0 — Flux complet bailleur (ce qu'il voit et fait)

Le bailleur ne touche jamais de code ni d'outil externe. Tout se passe
dans son panel Filament habituel — la visite 3D est simplement une nouvelle
section en bas de la page de son annonce.

#### Flux étape par étape

```
Il ouvre la fiche de son annonce dans Filament
         │
         ▼
Il voit une section "🏠 Visite Virtuelle 3D" en bas de page
         │
         ▼
┌─────────────────────────────────────────────┐
│  ÉTAPE 1 — Guide téléphone (accordéon)      │
│                                             │
│  📱 Android  → Google Camera → Photo Sphere │
│  🍎 iPhone   → Panorama 360°                │
│  📱 Samsung  → Google Camera recommandé     │
│                                             │
│  ✅ Conseils (lumière, centre pièce, etc.)  │
│                                             │
│  → Réduit par défaut, s'ouvre au clic       │
│    Pas intrusif pour les bailleurs avancés  │
└─────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────┐
│  ÉTAPE 2 — Upload des photos                │
│                                             │
│  ┌──────────────────────────────────────┐  │
│  │  📷 Glisse tes photos 360° ici       │  │
│  │     ou clique pour parcourir         │  │
│  │     JPG · WEBP · Max 30MB            │  │
│  └──────────────────────────────────────┘  │
│                                             │
│  Après drop des fichiers :                  │
│  ┌──────────────────────────────────────┐  │
│  │ 🖼 [aperçu] │ Salon          │ ↑ ↓ ✕ │  │
│  │ 🖼 [aperçu] │ Chambre        │ ↑ ↓ ✕ │  │
│  │ 🖼 [aperçu] │ Cuisine        │ ↑ ↓ ✕ │  │
│  └──────────────────────────────────────┘  │
│                                             │
│  → Nom auto-détecté depuis le nom de fichier│
│  → Modifiable en un clic                   │
│  → Réordonnable avec ↑ ↓                   │
│                                             │
│  [🚀 Publier le tour (3 pièces)]            │
└─────────────────────────────────────────────┘
         │
         ▼
    Laravel valide + uploade sur Cloudflare R2
    Sauvegarde le JSON de config en BDD
    Page rechargée automatiquement (2 secondes)
         │
         ▼
┌─────────────────────────────────────────────┐
│  ÉTAPE 3 — Éditeur de hotspots              │
│  (apparaît automatiquement après upload)    │
│                                             │
│  Sélecteur de pièces :                      │
│  [Salon ✓0] [Chambre ✓0] [Cuisine ✓0]      │
│                                             │
│  ┌──────────────────────────────────────┐  │
│  │                                      │  │
│  │     Vue 360° de la pièce active      │  │
│  │        (Pannellum — navigable)       │  │
│  │                              [➕ Lier]│  │
│  └──────────────────────────────────────┘  │
│                                             │
│  Il clique "➕ Lier une pièce" :            │
│  → Bordure orange apparaît                  │
│  → Message : "🎯 Clique sur la vue..."     │
│                                             │
│  Il clique sur la porte du salon :          │
│  → Dialog s'ouvre :                         │
│    "Pièce de destination" [Chambre ▼]      │
│    "Texte du lien" [Aller à la chambre]    │
│    [Confirmer]  [Annuler]                   │
│                                             │
│  Hotspot ajouté → visible dans la liste :   │
│  🔗 Aller à la chambre → Chambre  [✕]      │
│                                             │
│  Il répète pour chaque pièce               │
│                                             │
│  [💾 Sauvegarder les liens]                 │
└─────────────────────────────────────────────┘
         │
         ▼
✅ Tour publié — bouton "Visiter en Live 3D"
   visible sur l'annonce côté client
```

#### Wireframe de la page Filament

```
┌─────────────────────────────────────────────────────┐
│  Modifier l'annonce — Appartement T3 Bastos         │
│─────────────────────────────────────────────────────│
│  Titre        │ [Appartement T3 Bastos          ]   │
│  Prix         │ [150 000 FCFA                   ]   │
│  Description  │ [                               ]   │
│  ...autres champs existants...                      │
│─────────────────────────────────────────────────────│
│  🏠 Visite Virtuelle 3D                   ▼ replier │
│─────────────────────────────────────────────────────│
│                                                     │
│  ▶ 📱 Avant de commencer — Guide téléphone          │
│    (accordéon fermé par défaut)                     │
│                                                     │
│  ┌───────────────────────────────────────────────┐  │
│  │  📷 Glisse tes photos 360° ici                │  │
│  │     ou clique pour parcourir                  │  │
│  └───────────────────────────────────────────────┘  │
│                                                     │
│  ── Après publication du tour ──                    │
│                                                     │
│  ✅ Tour publié · 3 pièces  [🗑 Supprimer le tour]  │
│                                                     │
│  [Salon ✓2] [Chambre ✓1] [Cuisine ✓0]  ← badges   │
│              nombre de liens configurés             │
│                                                     │
│  ┌───────────────────────────────────────────────┐  │
│  │   Vue 360° — Salon          [➕ Lier une pièce]│  │
│  │                                               │  │
│  │         [ Pannellum viewer ]                  │  │
│  │                                               │  │
│  │  🔗 Aller à la chambre → Chambre        [✕]  │  │
│  │  🔗 Voir la cuisine → Cuisine           [✕]  │  │
│  └───────────────────────────────────────────────┘  │
│                                                     │
│              [💾 Sauvegarder les liens]             │
│                                                     │
└─────────────────────────────────────────────────────┘
```

#### Comportements importants à implémenter

```
Badge pièce     → "✓2" = 2 hotspots configurés sur cette pièce
                   Badge vert si ≥ 1 lien, gris si 0
                   Le bailleur sait d'un coup d'œil quelles pièces
                   sont reliées entre elles

Accordéon guide → Fermé par défaut pour ne pas encombrer
                   Bailleur expérimenté peut ignorer

Reload auto     → Après publication, rechargement 2s + message ✅
                   L'éditeur hotspots apparaît directement

Suppression     → Bouton "🗑 Supprimer le tour" demande confirmation
                   Supprime les images sur R2 + remet has_3d_tour = false

Éditeur         → Clic "Lier" désactive la navigation Pannellum
                   et active le mode placement (curseur crosshair)
                   Clic sur la vue → coordonnées pitch/yaw capturées
                   Dialog → confirmation → hotspot sauvegardé
```

---

### 5.1 — Installation

```bash
# Pannellum pour la preview dans Filament
npm install pannellum

# Ou via CDN dans le layout Filament custom (voir section 5.4)
```

### 5.2 — Resource Filament avec section Tour 3D

```php
<?php
// app/Filament/Resources/PropertyResource.php — ajouter la section tour

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\ViewField;

// Dans la méthode form() du Resource, ajouter après les sections existantes :

Section::make('🏠 Visite Virtuelle 3D')
    ->description('Offrez à vos locataires potentiels une immersion complète dans votre bien.')
    ->schema([

        // ── GUIDE TÉLÉPHONE ──────────────────────────────────────────────
        Section::make('📱 Avant de commencer — Comment prendre vos photos 360°')
            ->description('Lisez attentivement avant d\'uploader vos photos.')
            ->collapsible()
            ->schema([
                Placeholder::make('guide_android_google')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="space-y-4 text-sm">

                          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center gap-2 font-bold text-green-800 mb-2">
                              <span class="text-xl">🤖</span> Android — Google Camera (recommandé)
                            </div>
                            <ol class="list-decimal list-inside space-y-1 text-green-700">
                              <li>Téléchargez <strong>Google Camera</strong> (Play Store — gratuit)</li>
                              <li>Ouvrez l\'app → Appuyez sur <strong>Plus</strong> → <strong>Photo Sphere</strong></li>
                              <li>Placez-vous au centre de la pièce</li>
                              <li>Suivez les cercles à l\'écran en tournant lentement (360° complet)</li>
                              <li>Attendez le traitement automatique (10-30 secondes)</li>
                              <li>La photo apparaît dans votre Galerie avec une icône 🌐</li>
                            </ol>
                          </div>

                          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center gap-2 font-bold text-blue-800 mb-2">
                              <span class="text-xl">🍎</span> iPhone (iOS 14+)
                            </div>
                            <ol class="list-decimal list-inside space-y-1 text-blue-700">
                              <li>Ouvrez l\'app <strong>Appareil Photo</strong> natif</li>
                              <li>Sélectionnez le mode <strong>Panorama</strong></li>
                              <li>Faites un panorama <strong>complet à 360°</strong> en tournant sur vous-même</li>
                              <li>Gardez la flèche bien centrée sur la ligne horizontale</li>
                              <li>Tournez doucement et régulièrement (environ 15 secondes)</li>
                              <li>Alternative : téléchargez <strong>Panorama 360</strong> sur l\'App Store</li>
                            </ol>
                          </div>

                          <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                            <div class="flex items-center gap-2 font-bold text-orange-800 mb-2">
                              <span class="text-xl">📱</span> Samsung Galaxy
                            </div>
                            <ol class="list-decimal list-inside space-y-1 text-orange-700">
                              <li>Ouvrez l\'app <strong>Appareil Photo</strong> Samsung</li>
                              <li>Balayez vers <strong>Plus</strong> dans les modes</li>
                              <li>Sélectionnez <strong>Mise en scène</strong> ou <strong>Directeur</strong></li>
                              <li>Ou utilisez directement <strong>Google Camera</strong> (recommandé)</li>
                            </ol>
                          </div>

                          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <div class="font-bold text-gray-800 mb-2">✅ Conseils pour une bonne photo</div>
                            <ul class="list-disc list-inside space-y-1 text-gray-600">
                              <li>Prenez vos photos en <strong>pleine lumière</strong> (ouvrez les rideaux)</li>
                              <li>Placez-vous au <strong>centre exact</strong> de la pièce</li>
                              <li>Évitez de vous déplacer pendant la prise</li>
                              <li>Faites <strong>une photo par pièce</strong> (salon, chambre, cuisine, salle de bain...)</li>
                              <li>Format accepté : <strong>JPG ou WEBP</strong>, max 30 MB par photo</li>
                            </ul>
                          </div>

                        </div>
                    ')),
            ]),

        // ── UPLOAD DES SCÈNES ─────────────────────────────────────────────
        ViewField::make('tour_uploader')
            ->label('Étape 1 — Uploadez vos photos 360° (une par pièce)')
            ->view('filament.components.tour-uploader')
            ->viewData(['property' => fn($record) => $record]),

        // ── ÉDITEUR HOTSPOTS ──────────────────────────────────────────────
        ViewField::make('tour_hotspot_editor')
            ->label('Étape 2 — Reliez les pièces entre elles')
            ->view('filament.components.tour-hotspot-editor')
            ->viewData(['property' => fn($record) => $record])
            ->visible(fn($record) => $record?->has_3d_tour),

        // ── STATUT ────────────────────────────────────────────────────────
        Placeholder::make('tour_status')
            ->label('Statut du tour')
            ->content(fn($record) => $record?->has_3d_tour
                ? new HtmlString('<span class="text-green-600 font-bold">✅ Tour publié — ' . $record->tour_scenes_count . ' pièce(s)</span>')
                : new HtmlString('<span class="text-gray-400">Aucun tour pour le moment</span>')
            ),

    ])
    ->columnSpanFull(),
```

### 5.3 — Vue Blade : `tour-uploader.blade.php`

```blade
{{-- resources/views/filament/components/tour-uploader.blade.php --}}

<div
    x-data="tourUploader(@js($property?->id), @js($property?->tour_config))"
    class="space-y-4"
>
    {{-- Zone de drop --}}
    <div
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="handleDrop($event)"
        :class="isDragging ? 'border-primary-500 bg-primary-50' : 'border-gray-300'"
        class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors"
        @click="$refs.fileInput.click()"
    >
        <div class="text-4xl mb-2">📷</div>
        <p class="font-semibold text-gray-700">Glissez vos photos 360° ici ou cliquez pour parcourir</p>
        <p class="text-sm text-gray-400 mt-1">JPG · WEBP · Max 30 MB par photo</p>
        <input
            x-ref="fileInput"
            type="file"
            accept=".jpg,.jpeg,.webp"
            multiple
            class="hidden"
            @change="handleFileSelect($event)"
        />
    </div>

    {{-- Liste des scènes chargées --}}
    <template x-if="scenes.length > 0">
        <div class="space-y-2">
            <p class="text-sm font-medium text-gray-600">Pièces chargées :</p>
            <template x-for="(scene, index) in scenes" :key="scene.tempId">
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border">
                    {{-- Miniature --}}
                    <img :src="scene.preview" class="w-16 h-10 object-cover rounded" />

                    {{-- Nom de la pièce --}}
                    <input
                        x-model="scene.title"
                        type="text"
                        placeholder="Nom de la pièce (ex: Salon)"
                        class="flex-1 border border-gray-200 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-primary-500"
                    />

                    {{-- Ordre --}}
                    <div class="flex gap-1">
                        <button
                            @click="moveScene(index, -1)"
                            :disabled="index === 0"
                            class="p-1 text-gray-400 hover:text-gray-700 disabled:opacity-30"
                        >↑</button>
                        <button
                            @click="moveScene(index, 1)"
                            :disabled="index === scenes.length - 1"
                            class="p-1 text-gray-400 hover:text-gray-700 disabled:opacity-30"
                        >↓</button>
                    </div>

                    {{-- Supprimer --}}
                    <button @click="removeScene(index)" class="text-red-400 hover:text-red-600 p-1">✕</button>
                </div>
            </template>
        </div>
    </template>

    {{-- Bouton publier --}}
    <template x-if="scenes.length > 0">
        <button
            @click="publishTour()"
            :disabled="isPublishing || scenes.some(s => !s.title.trim())"
            class="w-full py-3 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white font-semibold rounded-xl transition-all flex items-center justify-center gap-2"
        >
            <template x-if="isPublishing">
                <span>⏳ Publication en cours...</span>
            </template>
            <template x-if="!isPublishing">
                <span>🚀 Publier le tour (<span x-text="scenes.length"></span> pièce<span x-show="scenes.length > 1">s</span>)</span>
            </template>
        </button>
    </template>

    {{-- Message succès --}}
    <template x-if="publishSuccess">
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700 font-medium text-center">
            ✅ Tour 3D publié avec succès ! Vos locataires peuvent maintenant faire la visite virtuelle.
        </div>
    </template>

    {{-- Message erreur --}}
    <template x-if="publishError">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 text-sm">
            ❌ <span x-text="publishError"></span>
        </div>
    </template>
</div>

<script>
function tourUploader(propertyId, existingConfig) {
    return {
        propertyId,
        scenes:         [],
        isDragging:     false,
        isPublishing:   false,
        publishSuccess: false,
        publishError:   null,

        handleDrop(event) {
            this.isDragging = false;
            this.addFiles(event.dataTransfer.files);
        },

        handleFileSelect(event) {
            this.addFiles(event.target.files);
        },

        addFiles(files) {
            Array.from(files).forEach(file => {
                const tempId  = 'temp_' + Date.now() + '_' + Math.random();
                const preview = URL.createObjectURL(file);
                const title   = file.name.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ');
                this.scenes.push({ tempId, file, preview, title, hotspots: [] });
            });
        },

        removeScene(index) {
            URL.revokeObjectURL(this.scenes[index].preview);
            this.scenes.splice(index, 1);
        },

        moveScene(index, direction) {
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= this.scenes.length) return;
            [this.scenes[index], this.scenes[newIndex]] = [this.scenes[newIndex], this.scenes[index]];
        },

        async publishTour() {
            this.isPublishing   = true;
            this.publishError   = null;
            this.publishSuccess = false;

            try {
                const formData = new FormData();

                this.scenes.forEach((scene, i) => {
                    formData.append(`scenes[${i}][title]`, scene.title);
                    formData.append(`scenes[${i}][image]`, scene.file);
                    scene.hotspots.forEach((hs, j) => {
                        formData.append(`scenes[${i}][hotspots][${j}][pitch]`,        hs.pitch);
                        formData.append(`scenes[${i}][hotspots][${j}][yaw]`,          hs.yaw);
                        formData.append(`scenes[${i}][hotspots][${j}][target_scene]`, hs.target_scene);
                        formData.append(`scenes[${i}][hotspots][${j}][label]`,        hs.label);
                    });
                });

                const res = await fetch(`/api/v1/properties/${this.propertyId}/tour/scenes`, {
                    method:  'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                    body:    formData,
                });

                if (!res.ok) {
                    const err = await res.json();
                    throw new Error(err.message ?? 'Erreur lors de la publication');
                }

                this.publishSuccess = true;
                this.scenes         = [];

                // Recharger la page après 2s pour afficher l'éditeur de hotspots
                setTimeout(() => window.location.reload(), 2000);

            } catch (e) {
                this.publishError = e.message;
            } finally {
                this.isPublishing = false;
            }
        },
    };
}
</script>
```

### 5.4 — Vue Blade : `tour-hotspot-editor.blade.php`

```blade
{{-- resources/views/filament/components/tour-hotspot-editor.blade.php --}}
{{-- Chargé uniquement si has_3d_tour = true --}}

<div x-data="hotspotEditor(@js($property?->tour_config), @js($property?->id))" class="space-y-4">

    <p class="text-sm text-gray-500">
        Sélectionnez une pièce, puis cliquez sur <strong>"Ajouter un lien"</strong>
        et cliquez sur l'endroit de la photo d'où vous voulez naviguer vers une autre pièce.
    </p>

    {{-- Sélecteur de scène active --}}
    <div class="flex gap-2 flex-wrap">
        <template x-for="scene in config.scenes" :key="scene.id">
            <button
                @click="activeSceneId = scene.id; loadScene(scene)"
                :class="activeSceneId === scene.id
                    ? 'bg-primary-600 text-white border-primary-600'
                    : 'bg-white text-gray-700 border-gray-200 hover:border-primary-400'"
                class="px-4 py-2 rounded-lg text-sm font-medium border transition-all flex items-center gap-2"
            >
                <span x-text="scene.title"></span>
                <span
                    x-show="scene.hotspots?.length"
                    class="bg-green-500 text-white text-xs rounded-full px-1.5 py-0.5"
                    x-text="scene.hotspots?.length"
                ></span>
            </button>
        </template>
    </div>

    {{-- Viewer 360° + overlay hotspot --}}
    <div class="relative rounded-xl overflow-hidden bg-gray-900" style="height: 450px;">
        <div id="hotspot-editor-viewer" class="w-full h-full"></div>

        {{-- Mode placement actif --}}
        <template x-if="isPlacing">
            <div class="absolute inset-0 pointer-events-none z-10 border-4 border-orange-400 rounded-xl flex items-start justify-center pt-4">
                <div class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm font-medium animate-pulse">
                    🎯 Cliquez sur la vue pour placer le lien
                </div>
            </div>
        </template>

        {{-- Boutons d'action --}}
        <div class="absolute top-3 right-3 flex gap-2 z-20">
            <button
                @click="startPlacing()"
                :class="isPlacing ? 'bg-orange-500' : 'bg-white text-gray-800 hover:bg-gray-100'"
                class="px-3 py-2 rounded-lg text-sm font-medium shadow transition-all text-white"
                x-text="isPlacing ? '❌ Annuler' : '➕ Ajouter un lien'"
            ></button>
        </div>

        {{-- Liste des hotspots de la scène active --}}
        <div class="absolute bottom-3 left-3 z-20 space-y-1 max-w-xs">
            <template x-for="(hs, i) in activeScene?.hotspots ?? []" :key="i">
                <div class="flex items-center gap-2 bg-black/70 text-white text-xs px-3 py-1.5 rounded-lg">
                    <span>🔗</span>
                    <span x-text="hs.label"></span>
                    <span class="text-gray-400">→</span>
                    <span x-text="getSceneTitle(hs.target_scene)" class="text-blue-300"></span>
                    <button
                        @click="removeHotspot(i)"
                        class="ml-auto text-red-400 hover:text-red-300 font-bold"
                    >✕</button>
                </div>
            </template>
        </div>
    </div>

    {{-- Dialog : choisir la scène cible et le label --}}
    <template x-if="showDialog">
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-2xl space-y-4">
                <h3 class="font-bold text-lg">Configurer le lien</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pièce de destination</label>
                    <select x-model="newHotspot.target_scene" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <template x-for="scene in config.scenes.filter(s => s.id !== activeSceneId)" :key="scene.id">
                            <option :value="scene.id" x-text="scene.title"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Texte du lien</label>
                    <input
                        x-model="newHotspot.label"
                        type="text"
                        placeholder="ex: Aller à la chambre"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                    />
                </div>

                <div class="flex gap-2">
                    <button
                        @click="confirmHotspot()"
                        :disabled="!newHotspot.target_scene || !newHotspot.label.trim()"
                        class="flex-1 bg-primary-600 text-white py-2 rounded-lg font-medium disabled:opacity-50"
                    >Confirmer</button>
                    <button @click="showDialog = false; isPlacing = false" class="flex-1 border border-gray-300 rounded-lg py-2">Annuler</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Bouton sauvegarder --}}
    <button
        @click="saveHotspots()"
        :disabled="isSaving"
        class="w-full py-3 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-semibold rounded-xl"
    >
        <span x-text="isSaving ? '⏳ Sauvegarde...' : '💾 Sauvegarder les liens'"></span>
    </button>

    <template x-if="saveSuccess">
        <p class="text-center text-green-600 font-medium">✅ Liens sauvegardés avec succès !</p>
    </template>
</div>

{{-- CDN Pannellum --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

<script>
function hotspotEditor(config, propertyId) {
    return {
        config,
        propertyId,
        activeSceneId: config?.scenes?.[0]?.id ?? null,
        viewer:        null,
        isPlacing:     false,
        showDialog:    false,
        isSaving:      false,
        saveSuccess:   false,
        pendingCoords: null,
        newHotspot:    { target_scene: '', label: '', pitch: 0, yaw: 0 },

        get activeScene() {
            return this.config?.scenes?.find(s => s.id === this.activeSceneId) ?? null;
        },

        init() {
            if (this.activeScene) this.loadScene(this.activeScene);
        },

        loadScene(scene) {
            this.activeSceneId = scene.id;
            if (this.viewer) { this.viewer.destroy(); this.viewer = null; }

            this.$nextTick(() => {
                const hotspotDefs = (scene.hotspots ?? []).map((hs, i) => ({
                    pitch:    hs.pitch,
                    yaw:      hs.yaw,
                    type:     'custom',
                    text:     hs.label,
                    cssClass: 'kh-hotspot',
                    id:       `hs_${i}`,
                }));

                this.viewer = pannellum.viewer('hotspot-editor-viewer', {
                    type:      'equirectangular',
                    panorama:  scene.image_url,
                    autoLoad:  true,
                    hfov:      110,
                    hotSpots:  hotspotDefs,
                    showControls: true,
                });

                // Clic sur la vue → placer le hotspot
                this.viewer.on('mousedown', (event) => {
                    if (!this.isPlacing) return;
                    const coords = this.viewer.mouseEventToCoords(event);
                    if (!coords) return;
                    this.pendingCoords = { pitch: coords[0], yaw: coords[1] };
                    this.newHotspot = { target_scene: this.config.scenes.find(s => s.id !== this.activeSceneId)?.id ?? '', label: '', pitch: coords[0], yaw: coords[1] };
                    this.showDialog = true;
                });
            });
        },

        startPlacing() {
            this.isPlacing = !this.isPlacing;
            this.showDialog = false;
        },

        confirmHotspot() {
            if (!this.activeScene) return;
            this.activeScene.hotspots = this.activeScene.hotspots ?? [];
            this.activeScene.hotspots.push({ ...this.newHotspot });
            this.showDialog  = false;
            this.isPlacing   = false;
            this.loadScene(this.activeScene); // Recharger avec le nouveau hotspot
        },

        removeHotspot(index) {
            if (!this.activeScene) return;
            this.activeScene.hotspots.splice(index, 1);
            this.loadScene(this.activeScene);
        },

        getSceneTitle(sceneId) {
            return this.config.scenes.find(s => s.id === sceneId)?.title ?? sceneId;
        },

        async saveHotspots() {
            if (!this.activeScene) return;
            this.isSaving    = true;
            this.saveSuccess = false;

            try {
                const res = await fetch(
                    `/api/v1/properties/${this.propertyId}/tour/scenes/${this.activeSceneId}/hotspots`,
                    {
                        method:  'PATCH',
                        headers: {
                            'Content-Type':  'application/json',
                            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.content,
                        },
                        body: JSON.stringify({ hotspots: this.activeScene.hotspots }),
                    }
                );
                if (!res.ok) throw new Error('Erreur serveur');
                this.saveSuccess = true;
                setTimeout(() => { this.saveSuccess = false; }, 3000);
            } catch (e) {
                alert('Erreur lors de la sauvegarde : ' + e.message);
            } finally {
                this.isSaving = false;
            }
        },
    };
}
</script>

<style>
.kh-hotspot {
    width: 32px !important;
    height: 32px !important;
    background: rgba(255,255,255,0.9) !important;
    border-radius: 50% !important;
    border: 3px solid #3b82f6 !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 16px !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
    transition: transform 0.2s !important;
}
.kh-hotspot:hover { transform: scale(1.2) !important; }
.kh-hotspot::after { content: "▶" !important; color: #3b82f6 !important; }
</style>
```

---

## 6. FRONTEND NEXT.JS — ÉDITEUR HOTSPOTS

> Le bailleur utilise **Filament PHP** pour tout gérer (voir section 5).
> Cette section décrit le composant de preview côté Next.js si tu veux
> l'exposer aussi dans un panel Next.js en parallèle.

```bash
npm install pannellum
```

```tsx
// components/owner/TourHotspotEditor.tsx — Version Next.js
// (Identique à la logique Alpine.js ci-dessus, portée en React)
// Voir section 7 pour le viewer complet
```

---

## 7. FRONTEND NEXT.JS — VIEWER CLIENT

### 7.1 — Composant `TourViewer`

```tsx
// components/property/TourViewer.tsx
'use client';

import { useEffect, useRef, useState, useCallback } from 'react';

interface Hotspot {
  pitch:        number;
  yaw:          number;
  target_scene: string;
  label:        string;
  type:         string;
}

interface Scene {
  id:           string;
  title:        string;
  image_url:    string;
  initial_view: { pitch: number; yaw: number; hfov: number };
  hotspots:     Hotspot[];
}

interface TourConfig {
  default_scene: string;
  scenes:        Scene[];
}

interface TourViewerProps {
  config:   TourConfig;
  onClose?: () => void;
}

export function TourViewer({ config, onClose }: TourViewerProps) {
  const containerRef              = useRef<HTMLDivElement>(null);
  const viewerRef                 = useRef<any>(null);
  const [currentId, setCurrentId] = useState(config.default_scene ?? config.scenes[0]?.id);
  const [isLoading, setIsLoading] = useState(true);

  const currentScene = config.scenes.find(s => s.id === currentId) ?? config.scenes[0];

  const loadScene = useCallback((scene: Scene) => {
    if (!containerRef.current) return;
    setIsLoading(true);

    // Destroy le viewer précédent
    if (viewerRef.current) {
      try { viewerRef.current.destroy(); } catch (_) {}
      viewerRef.current = null;
    }

    import('pannellum').then(({ default: pannellum }) => {
      viewerRef.current = pannellum.viewer(containerRef.current!, {
        type:      'equirectangular',
        panorama:  scene.image_url,
        autoLoad:  true,
        hfov:      scene.initial_view?.hfov ?? 110,
        pitch:     scene.initial_view?.pitch ?? 0,
        yaw:       scene.initial_view?.yaw ?? 0,
        autoRotate: -1.5,
        showControls: true,
        hotSpots: scene.hotspots.map(hs => ({
          pitch:    hs.pitch,
          yaw:      hs.yaw,
          type:     'custom',
          text:     hs.label,
          cssClass: 'kh-tour-hotspot',
          clickHandlerFunc: () => {
            setCurrentId(hs.target_scene);
          },
        })),
      });

      viewerRef.current.on('load', () => setIsLoading(false));
    });
  }, []);

  useEffect(() => {
    if (currentScene) loadScene(currentScene);
    return () => {
      try { viewerRef.current?.destroy(); } catch (_) {}
    };
  }, [currentId]);

  // Fermeture avec Escape
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose?.();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [onClose]);

  return (
    <div className="relative w-full h-full bg-black rounded-2xl overflow-hidden">

      {/* Loading overlay */}
      {isLoading && (
        <div className="absolute inset-0 flex items-center justify-center z-20 bg-black/70">
          <div className="flex flex-col items-center gap-3 text-white">
            <div className="w-10 h-10 border-4 border-white/30 border-t-white rounded-full animate-spin" />
            <span className="text-sm font-medium">Chargement de la visite...</span>
          </div>
        </div>
      )}

      {/* Viewer Pannellum */}
      <div ref={containerRef} className="w-full h-full" />

      {/* Header : titre + bouton fermer */}
      <div className="absolute top-0 left-0 right-0 z-10 flex items-center justify-between px-4 py-3 bg-gradient-to-b from-black/60 to-transparent">
        <div className="flex items-center gap-2 text-white">
          <span className="text-lg">🏠</span>
          <span className="font-semibold text-sm">
            {currentScene?.title}
          </span>
          <span className="text-white/50 text-xs">
            ({config.scenes.indexOf(currentScene!) + 1}/{config.scenes.length})
          </span>
        </div>
        {onClose && (
          <button
            onClick={onClose}
            className="text-white/80 hover:text-white bg-black/40 rounded-full w-8 h-8 flex items-center justify-center text-lg transition-colors"
            aria-label="Fermer la visite"
          >
            ✕
          </button>
        )}
      </div>

      {/* Navigation bas : sélecteur de pièces */}
      {config.scenes.length > 1 && (
        <div className="absolute bottom-0 left-0 right-0 z-10 px-4 py-3 bg-gradient-to-t from-black/70 to-transparent">
          <div className="flex gap-2 justify-center flex-wrap">
            {config.scenes.map(scene => (
              <button
                key={scene.id}
                onClick={() => setCurrentId(scene.id)}
                className={`px-3 py-1.5 rounded-full text-xs font-medium transition-all ${
                  scene.id === currentId
                    ? 'bg-white text-gray-900 shadow-lg scale-105'
                    : 'bg-black/50 text-white hover:bg-black/70 border border-white/20'
                }`}
              >
                {scene.title}
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Instructions */}
      {!isLoading && (
        <div className="absolute bottom-14 right-4 z-10">
          <div className="bg-black/50 text-white text-xs px-2 py-1 rounded-lg text-center">
            🖱️ Glissez pour explorer · 🔗 Cliquez les liens pour naviguer
          </div>
        </div>
      )}
    </div>
  );
}
```

### 7.2 — CSS Hotspots (global CSS)

```css
/* styles/tour.css */
.kh-tour-hotspot {
  width: 36px !important;
  height: 36px !important;
  background: rgba(255, 255, 255, 0.95) !important;
  border-radius: 50% !important;
  border: 3px solid #3b82f6 !important;
  cursor: pointer !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3), 0 4px 12px rgba(0, 0, 0, 0.4) !important;
  transition: all 0.2s ease !important;
  animation: pulse-hotspot 2s infinite !important;
}

.kh-tour-hotspot:hover {
  transform: scale(1.2) !important;
  box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.4), 0 4px 16px rgba(0, 0, 0, 0.5) !important;
}

.kh-tour-hotspot::after {
  content: "▶" !important;
  color: #3b82f6 !important;
  font-size: 14px !important;
}

@keyframes pulse-hotspot {
  0%, 100% { box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3); }
  50%       { box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.1); }
}

/* Tooltip du hotspot */
.pnlm-hotspot-base .pnlm-tooltip span {
  background:    rgba(0, 0, 0, 0.85) !important;
  border-radius: 8px !important;
  padding:       6px 10px !important;
  font-size:     12px !important;
  white-space:   nowrap !important;
  font-family:   inherit !important;
}
```

---

## 8. INTÉGRATION DANS ADDETAILS.TSX

```tsx
// components/property/AdDetails.tsx — ajouter le bloc tour 3D

'use client';

import { useState } from 'react';
import { TourViewer } from './TourViewer';

// Dans le composant AdDetails, ajouter :

export function AdDetails({ property }: { property: Property }) {
  const [showTour, setShowTour] = useState(false);

  return (
    <div>
      {/* ... ton contenu existant ... */}

      {/* ── BOUTON VISITE 3D ──────────────────────────────────────────── */}
      {property.has_3d_tour && property.tour_config && (
        <div className="my-6">
          <button
            onClick={() => setShowTour(true)}
            className="
              group relative w-full overflow-hidden
              bg-gradient-to-r from-blue-600 to-indigo-600
              hover:from-blue-500 hover:to-indigo-500
              text-white font-bold py-4 px-6 rounded-2xl
              shadow-lg hover:shadow-xl
              transition-all duration-300
              flex items-center justify-center gap-3
              text-base
            "
          >
            {/* Effet shimmer */}
            <span className="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700" />

            <span className="text-2xl animate-pulse">🔴</span>
            <span>Visiter ce bien en Live 3D</span>
            <span className="text-white/70 text-sm font-normal">
              · {property.tour_scenes_count} pièce{property.tour_scenes_count > 1 ? 's' : ''}
            </span>
            <span className="ml-1 text-xl">→</span>
          </button>

          <p className="text-center text-xs text-gray-400 mt-2">
            ↕ Explorez chaque pièce en 360° sans vous déplacer
          </p>
        </div>
      )}

      {/* ... reste du contenu ... */}

      {/* ── MODAL VISITE VIRTUELLE ────────────────────────────────────── */}
      {showTour && property.tour_config && (
        <div
          className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
          onClick={(e) => { if (e.target === e.currentTarget) setShowTour(false); }}
          role="dialog"
          aria-modal="true"
          aria-label="Visite virtuelle 3D"
        >
          <div className="w-full max-w-5xl h-[80vh] rounded-2xl overflow-hidden shadow-2xl">
            <TourViewer
              config={property.tour_config}
              onClose={() => setShowTour(false)}
            />
          </div>
        </div>
      )}
    </div>
  );
}
```

### 8.1 — Type TypeScript `Property` mis à jour

```typescript
// types/property.ts — ajouter

export interface TourHotspot {
  pitch:        number;
  yaw:          number;
  target_scene: string;
  label:        string;
  type:         'scene';
}

export interface TourScene {
  id:           string;
  title:        string;
  image_url:    string;
  initial_view: { pitch: number; yaw: number; hfov: number };
  hotspots:     TourHotspot[];
}

export interface TourConfig {
  default_scene: string;
  scenes:        TourScene[];
}

export interface Property {
  // ... champs existants ...
  has_3d_tour:        boolean;
  tour_config:        TourConfig | null;
  tour_scenes_count:  number;
  tour_published_at:  string | null;
}
```

---

## 9. SÉCURITÉ & VALIDATION

### 9.1 — Validation côté Laravel

```php
// ✅ Ces validations sont déjà dans TourController (section 4.2)
// Rappel des points critiques :

// 1. Vérification MIME réelle (pas juste l'extension)
$mime = $file->getMimeType();  // getimagesize() en interne
// → Rejette les fichiers .jpg qui sont en réalité des .php ou .exe

// 2. Vérification que l'utilisateur est bien le propriétaire
$this->authorize('update', $property);

// 3. Limite de taille (30MB par image)
'max:30720'

// 4. Limite du nombre de scènes (max 20)
'scenes' => ['required', 'array', 'min:1', 'max:20']

// 5. Validation des coordonnées hotspot (valeurs physiquement possibles)
'pitch' => ['numeric', 'between:-90,90']
'yaw'   => ['numeric', 'between:-180,180']

// 6. Rate limiting sur l'upload
Route::post(...)->middleware('throttle:10,1');
```

### 9.2 — CSP Next.js — mise à jour `next.config.ts`

```typescript
// Dans next.config.ts, ajouter au cspHeader :
// img-src : ajouter ton domaine S3/Cloudflare R2
// ex: https://*.r2.cloudflarestorage.com  ou  https://cdn.keyhome.app

const cspHeader = [
  // ... directives existantes ...
  `img-src 'self' blob: data: ... https://cdn.keyhome.app`,
].join('; ');
```

---

## 10. FLUTTER — PRÉPARATION MOBILE

> **Phase 2 — À implémenter plus tard.**
> L'API Laravel est déjà prête. Voici ce qu'il faudra faire côté Flutter.

### 10.1 — Dépendances prévues

```yaml
# pubspec.yaml (à ajouter en Phase 2)
dependencies:
  webview_flutter: ^4.7.0    # Viewer Pannellum via WebView
  http: ^1.2.0
```

### 10.2 — Architecture prévue

```dart
// Appel API : GET /api/v1/properties/{id}/tour
// → Réceptionne le tourConfig JSON
// → Génère une page HTML Pannellum à la volée
// → L'affiche dans une WebView fullscreen

// Fichier à créer en Phase 2 :
// lib/features/property/presentation/pages/tour_3d_page.dart
```

### 10.3 — Endpoint déjà prêt

```
GET /api/v1/properties/{id}/tour
→ Retourne exactement le même JSON utilisé par Next.js
→ Flutter n'a qu'à parser et rendre
```

---

## 11. CHECKLIST DÉPLOIEMENT

### Backend Laravel

- [ ] Migration exécutée : `php artisan migrate`
- [ ] `TourService` lié dans `AppServiceProvider`
- [ ] `TourController` enregistré dans `routes/api.php`
- [ ] Policy `PropertyPolicy` avec méthode `update` correcte
- [ ] Disque `s3` configuré dans `config/filesystems.php`
- [ ] Variables S3 dans `.env` : `AWS_BUCKET`, `AWS_URL`, `AWS_REGION`
- [ ] Rate limiting actif sur la route upload

### Panel Filament

- [ ] Vues Blade créées : `tour-uploader.blade.php` et `tour-hotspot-editor.blade.php`
- [ ] Section Tour ajoutée dans `PropertyResource`
- [ ] CDN Pannellum chargé dans le layout Filament ou dans les vues
- [ ] Meta CSRF token présent dans le layout Filament

### Frontend Next.js

- [ ] `npm install pannellum`
- [ ] `TourViewer.tsx` créé dans `components/property/`
- [ ] CSS hotspots importé dans `styles/globals.css` ou `layout.tsx`
- [ ] Bouton "Visiter en Live 3D" ajouté dans `AdDetails.tsx`
- [ ] Type `TourConfig` ajouté dans `types/property.ts`
- [ ] CSP mis à jour avec le domaine S3/CDN dans `next.config.ts`
- [ ] Import dynamique Pannellum (`ssr: false`) vérifié

### Tests à effectuer

- [ ] Bailleur uploade 1 photo → tour créé, visible sur la fiche annonce
- [ ] Bailleur uploade 3 photos → navigation entre pièces fonctionnelle
- [ ] Bailleur ajoute un hotspot → lien visible et cliquable dans le viewer
- [ ] Client clique "Visiter en Live 3D" → modal s'ouvre, photo 360° chargée
- [ ] Client navigue d'une pièce à l'autre via les hotspots
- [ ] Client ferme avec ✕ ou touche Echap
- [ ] Test mobile : modal responsive sur 375px
- [ ] Propriété sans tour → bouton absent sur AdDetails
- [ ] Upload d'un fichier non-image → erreur 422 propre

---

*Documentation générée pour KeyHome — Système de visite virtuelle 3D*
*Stack : Laravel · Filament PHP · Next.js · Pannellum.js*
*Dernière mise à jour : Mars 2026*
