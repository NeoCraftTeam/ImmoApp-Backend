# Guide d'Implémentation des Visites Virtuelles 360° pour KeyHome

## Introduction

L'intégration de visites virtuelles à 360° dans l'application KeyHome représente un avantage concurrentiel majeur, particulièrement dans les marchés où les infrastructures de transport peuvent rendre les visites physiques fastidieuses. Cette fonctionnalité permettra aux utilisateurs de KeyHome de visualiser des propriétés de manière immersive, réduisant ainsi le nombre de visites physiques inutiles et améliorant l'expérience utilisateur. De plus, elle ouvre la voie à un modèle de "listing premium" pour les agents, leur permettant de monétiser cette valeur ajoutée.

Ce guide détaille les étapes nécessaires pour implémenter cette fonctionnalité, couvrant les modifications du backend (Laravel avec Spatie Media Library) et l'intégration frontend (React avec Pannellum).

## 1. Implémentation Backend

Le backend sera responsable de la gestion du stockage des images 360°, de l'organisation des galeries d'images ordonnées, et de la logique métier liée aux listings premium.

### 1.1. Configuration de Spatie Media Library et du Modèle `Ad`

Le modèle `Ad` (`app/Models/Ad.php`) a été étendu pour supporter de nouvelles collections de médias et des méthodes de gestion. Spatie Media Library est déjà en place, nous allons donc ajouter une nouvelle collection spécifiquement pour les images 360° et améliorer la gestion des images de galerie existantes.

**Modifications dans `app/Models/Ad.php` :**

1.  **Nouvelle collection pour les images 360° (`tours_360`)** :
    Cette collection est configurée pour n'accepter qu'un seul fichier par annonce (`singleFile()`) et des types MIME spécifiques (`image/jpeg`, `image/png`).

2.  **Amélioration de la collection `images`** :
    La collection `images` existante a été mise à jour pour inclure une conversion `full_hd` et pour permettre l'ajout de propriétés personnalisées (comme l'ordre des images).

3.  **Nouvelles méthodes dans le modèle `Ad`** :
    *   `get360Image()` : Récupère l'image 360° associée à l'annonce.
    *   `has360Tour()` : Vérifie si l'annonce dispose d'une visite 360°.
    *   `getOrderedImages()` : Récupère les images de la galerie triées selon un ordre personnalisé.
    *   `updateImageOrder(array $orderMap)` : Met à jour l'ordre des images dans la galerie.

```php
// app/Models/Ad.php

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Ad extends Model implements HasMedia
{
    use InteractsWithMedia;

    // ... autres propriétés et méthodes ...

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(\'images\')
            ->onlyKeepLatest(10)
            ->useDisk(\'public\')
            ->registerMediaConversions(function (Media $media = null) {
                $this->addMediaConversion(\'full_hd\')
                    ->width(1920)
                    ->height(1080)
                    ->format(\'webp\')
                    ->quality(85);
            });

        $this->addMediaCollection(\'tours_360\')
            ->singleFile() // Une seule image 360° par annonce
            ->acceptsMimeTypes([\'image/jpeg\', \'image/png\']) // Accepter JPEG et PNG pour les 360°
            ->useDisk(\'public\');

        $this->addMediaCollection(\'property_condition\')
            ->singleFile()
            ->acceptsMimeTypes([\'application/pdf\'])
            ->useDisk(\'public\');
    }

    // Les conversions générales pour les images standard sont définies ici
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(\'thumb\')
            ->nonQueued()
            ->width(300)
            ->height(300)
            ->format(\'webp\')
            ->quality(80);

        $this->addMediaConversion(\'medium\')
            ->nonQueued()
            ->width(800)
            ->height(600)
            ->format(\'webp\')
            ->quality(80);

        $this->addMediaConversion(\'large\')
            ->queued()
            ->width(1200)
            ->height(900)
            ->format(\'webp\')
            ->quality(85);
    }

    /**
     * Obtenir l\'image 360° de l\'annonce
     */
    public function get360Image(): ?Media
    {
        return $this->getFirstMedia(\'tours_360\');
    }

    /**
     * Vérifier si l\'annonce a une visite 360°
     */
    public function has360Tour(): bool
    {
        return $this->getFirstMedia(\'tours_360\') !== null;
    }

    /**
     * Obtenir les images de la galerie avec ordre personnalisé
     * Retourne les images avec leur ordre personnalisé (via custom_order dans les métadonnées)
     */
    public function getOrderedImages(): \Illuminate\Support\Collection
    {
        return $this->getMedia(\'images\')
            ->sortBy(function (Media $media) {
                return (int) ($media->getCustomProperty(\'order\') ?? 0);
            })
            ->values();
    }

    /**
     * Mettre à jour l\'ordre des images
     * @param array<string, int> $orderMap Tableau associatif : media_id => order
     */
    public function updateImageOrder(array $orderMap): void
    {
        foreach ($orderMap as $mediaId => $order) {
            $media = Media::find($mediaId);
            if ($media && $media->model_id === $this->id && $media->collection_name === \'images\') {
                $media->setCustomProperty(\'order\', $order);
                $media->save();
            }
        }
    }

    // ... autres méthodes ...
}
```

### 1.2. Migrations de Base de Données

Deux nouvelles migrations ont été créées pour ajouter les champs nécessaires au modèle `Ad` :

1.  **`2026_03_02_add_360_tour_to_ads.php`** :
    Ajoute les colonnes `has_360_tour` (booléen pour indiquer la présence d'une visite 360°), `is_premium_listing` (booléen pour le statut premium) et `tour_360_added_at` (timestamp de l'ajout de la visite 360°).

```php
// database/migrations/2026_03_02_add_360_tour_to_ads.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(\'ad\', function (Blueprint $table) {
            $table->boolean(\'has_360_tour\')->default(false)->after(\'is_boosted\');
            $table->boolean(\'is_premium_listing\')->default(false)->after(\'has_360_tour\');
            $table->timestamp(\'tour_360_added_at\')->nullable()->after(\'is_premium_listing\');
            $table->index(\'has_360_tour\');
            $table->index(\'is_premium_listing\');
        });
    }

    public function down(): void
    {
        Schema::table(\'ad\', function (Blueprint $table) {
            $table->dropIndex([\'has_360_tour\']);
            $table->dropIndex([\'is_premium_listing\']);
            $table->dropColumn([\'has_360_tour\', \'is_premium_listing\', \'tour_360_added_at\']);
        });
    }
};
```

2.  **`2026_03_02_add_premium_listing_fields_to_ads.php`** :
    Ajoute la colonne `premium_listing_expires_at` pour gérer l'expiration des listings premium.

```php
// database/migrations/2026_03_02_add_premium_listing_fields_to_ads.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(\'ad\', function (Blueprint $table) {
            $table->timestamp(\'premium_listing_expires_at\')->nullable()->after(\'is_premium_listing\');
            $table->index(\'premium_listing_expires_at\');
        });
    }

    public function down(): void
    {
        Schema::table(\'ad\', function (Blueprint $table) {
            $table->dropIndex([\'premium_listing_expires_at\']);
            $table->dropColumn([\'premium_listing_expires_at\']);
        });
    }
};
```

Après avoir créé ces fichiers, exécutez les migrations :

```bash
php artisan migrate
```

### 1.3. Contrôleurs et API

Un nouveau contrôleur, `AdMediaController` (`app/Http/Controllers/AdMediaController.php`), a été créé pour gérer toutes les opérations liées aux médias des annonces, y compris les images 360° et les galeries d'images ordonnées.

**`app/Http/Controllers/AdMediaController.php` :**

Ce contrôleur inclut les méthodes suivantes :

*   `uploadImage(Request $request, Ad $ad)` : Pour l'upload d'images de galerie standard, avec support de l'ordre.
*   `upload360Image(Request $request, Ad $ad)` : Pour l'upload d'une image 360°. Elle supprime l'ancienne image 360° si elle existe et met à jour le statut `has_360_tour` de l'annonce.
*   `getImages(Ad $ad)` : Récupère toutes les images de la galerie, triées par l'ordre personnalisé.
*   `get360Image(Ad $ad)` : Récupère l'URL de l'image 360°.
*   `updateImageOrder(Request $request, Ad $ad)` : Met à jour l'ordre des images dans la galerie.
*   `deleteImage(Ad $ad, $mediaId)` : Supprime une image de la galerie.
*   `delete360Image(Ad $ad)` : Supprime l'image 360° et met à jour le statut de l'annonce.
*   `downloadImage(Ad $ad, $mediaId)` : Permet de télécharger une image spécifique.

```php
// app/Http/Controllers/AdMediaController.php

// ... (code du contrôleur comme généré précédemment) ...
```

**Routes API (`routes/ad-media-routes.php` et `routes/api.php`) :**

Un fichier de routes séparé, `ad-media-routes.php`, a été créé pour organiser les routes liées aux médias des annonces. Ce fichier doit être inclus dans `routes/api.php`.

**`routes/ad-media-routes.php` :**

```php
// routes/ad-media-routes.php

use App\Http\Controllers\AdMediaController;
use Illuminate\Support\Facades\Route;

Route::prefix(\'ads/{ad}/media\')->middleware(\'auth:sanctum\')->controller(AdMediaController::class)->group(function (): void {
    // Galerie d\'images standard
    Route::post(\'images\', \'uploadImage\')->name(\'ads.media.upload-image\');
    Route::get(\'images\', \'getImages\')->name(\'ads.media.get-images\');
    Route::put(\'images/order\', \'updateImageOrder\')->name(\'ads.media.update-image-order\');
    Route::delete(\'images/{mediaId}\' , \'deleteImage\')->name(\'ads.media.delete-image\');
    Route::get(\'images/{mediaId}/download\', \'downloadImage\')->name(\'ads.media.download-image\');

    // Images 360°
    Route::post(\'360\', \'upload360Image\')->name(\'ads.media.upload-360\');
    Route::get(\'360\', \'get360Image\')->name(\'ads.media.get-360\');
    Route::delete(\'360\', \'delete360Image\')->name(\'ads.media.delete-360\');
});
```

**Inclusion dans `routes/api.php` :**

Assurez-vous d'inclure ce fichier dans votre `routes/api.php` pour que les routes soient enregistrées. Par exemple, vous pouvez l'ajouter dans le groupe `v1` :

```php
// routes/api.php

// ... autres imports et routes ...

Route::prefix(\'v1\')->group(function (): void {
    // ... autres routes v1 ...

    // Inclure les routes de gestion des médias d\'annonces
    require __DIR__.\'/ad-media-routes.php\';
});
```

### 1.4. Logique de Listing Premium

Un service `PremiumListingService` (`app/Services/PremiumListingService.php`) a été créé pour encapsuler la logique métier des listings premium, y compris la gestion des paiements et des durées.

**`app/Services/PremiumListingService.php` :**

Ce service gère :

*   La récupération du prix et de la durée des listings premium (configurables via la table `settings`).
*   La vérification si un utilisateur peut créer un listing premium (basé sur l'abonnement ou le paiement à l'unité).
*   L'activation, la confirmation de paiement, le renouvellement et l'annulation des listings premium.
*   La vérification du statut actif d'un listing premium.

```php
// app/Services/PremiumListingService.php

// ... (code du service comme généré précédemment) ...
```

**Intégration dans les contrôleurs (exemple) :**

Vous devrez injecter ce service dans vos contrôleurs (par exemple, `AdController` ou un nouveau contrôleur `PremiumListingController`) pour gérer les actions liées aux listings premium. Par exemple, lors de l'upload d'une image 360°, vous pourriez vérifier si l'annonce est premium ou proposer de l'activer.

```php
// Exemple d\'utilisation dans un contrôleur

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Services\PremiumListingService;
use Illuminate\Http\Request;

class AdController extends Controller
{
    protected $premiumListingService;

    public function __construct(PremiumListingService $premiumListingService)
    {
        $this->premiumListingService = $premiumListingService;
    }

    public function store(Request $request)
    {
        // ... logique de création d\'annonce ...

        $ad = Ad::create(/* ... */);

        // Si l\'utilisateur souhaite un listing premium avec 360°
        if ($request->has(\'activate_premium_360\') && $request->input(\'activate_premium_360\')) {
            $result = $this->premiumListingService->activatePremiumListing($ad, auth()->user());
            if (!$result[\'success\']) {
                // Gérer l\'erreur ou informer l\'utilisateur
            }
        }

        return response()->json($ad, 201);
    }

    public function show(Ad $ad)
    {
        // ... récupérer l\'annonce ...

        $ad->load(\'media\'); // Charger les médias

        return response()->json([
            \'ad\' => $ad,
            \'has_360_tour\' => $ad->has360Tour(),
            \'is_premium_listing\' => $this->premiumListingService->isPremiumListingActive($ad),
            \'premium_listing_expires_at\' => $ad->premium_listing_expires_at,
            \'images\' => $ad->getOrderedImages()->map(fn ($media) => $media->toArray()),
            \'image_360_url\' => $ad->get360Image() ? $ad->get360Image()->getUrl() : null,
        ]);
    }
}
```

## 2. Intégration Frontend (React)

Le frontend utilisera des composants React pour permettre l'upload des images 360° et des galeries, ainsi que pour afficher les visites virtuelles.

### 2.1. Composant `PannellumViewer360.jsx`

Ce composant est un wrapper React pour la bibliothèque JavaScript Pannellum, permettant d'afficher facilement des images panoramiques 360°.

**`resources/js/components/PannellumViewer360.jsx` :**

```jsx
// resources/js/components/PannellumViewer360.jsx

import React, { useEffect, useRef } from 'react';

export default function PannellumViewer360({
  imageUrl,
  title = 'Visite virtuelle 360°',
  autoLoad = true,
  onReady = null,
  onError = null,
  config = {},
}) {
  const containerRef = useRef(null);
  const viewerRef = useRef(null);

  useEffect(() => {
    // Charger Pannellum depuis CDN si pas déjà chargé
    if (!window.pannellum) {
      const script = document.createElement('script');
      script.src = 'https://cdn.pannellum.org/2.5/pannellum.js';
      script.async = true;
      script.onload = () => {
        initializeViewer();
      };
      script.onerror = () => {
        console.error('Erreur lors du chargement de Pannellum');
        if (onError) onError(new Error('Pannellum CDN non disponible'));
      };
      document.head.appendChild(script);

      // Charger CSS si pas déjà chargé
      if (!document.querySelector('link[href*="pannellum.css"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdn.pannellum.org/2.5/pannellum.css';
        document.head.appendChild(link);
      }
    } else {
      initializeViewer();
    }

    return () => {
      // Nettoyer le viewer si le composant est démonté
      if (viewerRef.current) {
        viewerRef.current.destroy?.();
      }
    };
  }, [imageUrl]);

  const initializeViewer = () => {
    if (!containerRef.current || !window.pannellum) return;

    try {
      const defaultConfig = {
        'default': {
          'firstScene': 'scene1',
          'author': 'KeyHome',
          'title': title,
        },
        'scenes': {
          'scene1': {
            'title': title,
            'type': 'equirectangular',
            'panorama': imageUrl,
            'hotSpots': [],
          },
        },
      };

      const finalConfig = {
        ...defaultConfig,
        ...config,
      };

      viewerRef.current = window.pannellum.viewer(
        containerRef.current,
        finalConfig
      );

      viewerRef.current.on('load', () => {
        console.log('Pannellum viewer chargé');
        if (onReady) onReady(viewerRef.current);
      });

      viewerRef.current.on('error', (error) => {
        console.error('Erreur Pannellum:', error);
        if (onError) onError(error);
      });
    } catch (error) {
      console.error('Erreur lors de l\'initialisation de Pannellum:', error);
      if (onError) onError(error);
    }
  };

  return (
    <div
      ref={containerRef}
      style={{
        width: '100%',
        height: '100%',
        minHeight: '500px',
        borderRadius: '8px',
        overflow: 'hidden',
        backgroundColor: '#000',
      }}
      className="pannellum-container"
    />
  );
}
```

**Utilisation du composant `PannellumViewer360` :**

```jsx
import PannellumViewer360 from './components/PannellumViewer360';

function AdDetailPage({ ad }) {
  // ...
  return (
    <div>
      {ad.has_360_tour && ad.image_360_url && (
        <div style={{ width: '100%', height: '500px' }}>
          <PannellumViewer360 imageUrl={ad.image_360_url} title={ad.title} />
        </div>
      )}
      {/* ... autres détails de l\'annonce ... */}
    </div>
  );
}
```

### 2.2. Composant `Upload360Image.jsx`

Ce composant permet aux utilisateurs d'uploader facilement une image 360° pour une annonce, avec validation de la taille et du type de fichier, et affichage de la progression.

**`resources/js/components/Upload360Image.jsx` :**

```jsx
// resources/js/components/Upload360Image.jsx

import React, { useState, useRef } from 'react';

// ... (code du composant comme généré précédemment) ...
```

**Utilisation du composant `Upload360Image` :**

```jsx
import Upload360Image from './components/Upload360Image';

function AdEditPage({ adId }) {
  const handleUploadSuccess = (media) => {
    console.log('Image 360° uploadée avec succès:', media);
    // Mettre à jour l\'état de l\'annonce pour refléter la nouvelle image 360°
  };

  const handleUploadError = (error) => {
    console.error('Erreur lors de l\'upload de l\'image 360°:', error);
  };

  return (
    <div>
      <h2>Uploader une image 360°</h2>
      <Upload360Image
        adId={adId}
        onSuccess={handleUploadSuccess}
        onError={handleUploadError}
      />
    </div>
  );
}
```

### 2.3. Composant `OrderedImageGallery.jsx`

Ce composant gère l'affichage et la réorganisation des images de la galerie d'une annonce, avec des fonctionnalités de glisser-déposer et de suppression.

**`resources/js/components/OrderedImageGallery.jsx` :**

```jsx
// resources/js/components/OrderedImageGallery.jsx

import React, { useState, useEffect } from 'react';

// ... (code du composant comme généré précédemment) ...
```

**Utilisation du composant `OrderedImageGallery` :**

```jsx
import OrderedImageGallery from './components/OrderedImageGallery';

function AdEditPage({ adId, initialImages }) {
  const [images, setImages] = useState(initialImages);

  const handleOrderChange = (newOrderedImages) => {
    setImages(newOrderedImages);
    // Envoyer la nouvelle commande au backend via l\'API
    // Exemple: axios.put(`/api/v1/ads/${adId}/media/images/order`, { images: newOrderedImages.map(img => ({ id: img.id, order: img.order })) });
  };

  const handleImageDelete = (deletedImageId) => {
    console.log('Image supprimée:', deletedImageId);
    // Recharger les images ou filtrer l\'état local
  };

  const handleImageUpload = (newImage) => {
    console.log('Nouvelle image uploadée:', newImage);
    // Recharger les images ou ajouter à l\'état local
  };

  return (
    <div>
      <h2>Gérer la galerie d\'images</h2>
      <OrderedImageGallery
        adId={adId}
        images={images}
        onOrderChange={handleOrderChange}
        onImageDelete={handleImageDelete}
        onImageUpload={handleImageUpload}
        editable={true} // Permettre l\'édition (upload, suppression, réorganisation)
      />
    </div>
  );
}
```

## 3. Guide d'Utilisation et Flux de Travail

Voici un aperçu du flux de travail pour les agents et les utilisateurs finaux :

### Pour les Agents/Bailleurs (Création et Gestion d'Annonces)

1.  **Création d'Annonce** : L'agent crée une annonce comme d'habitude.
2.  **Upload d'Images 360°** : Via une interface d'édition d'annonce, l'agent utilise le composant `Upload360Image` pour téléverser son image panoramique. Cela mettra à jour le champ `has_360_tour` de l'annonce.
3.  **Gestion de la Galerie d'Images** : L'agent utilise le composant `OrderedImageGallery` pour ajouter, supprimer et réorganiser les images standard de l'annonce.
4.  **Activation du Listing Premium** : L'agent peut choisir d'activer le "listing premium" pour son annonce. Cela déclenchera un processus de paiement (via FedaPay) géré par le `PremiumListingService`. Une fois le paiement confirmé, le champ `is_premium_listing` sera mis à jour et une date d'expiration sera définie.

### Pour les Utilisateurs Finaux (Consultation d'Annonces)

1.  **Recherche d'Annonces** : Les utilisateurs naviguent et recherchent des annonces.
2.  **Identification des Listings Premium** : Les annonces avec `is_premium_listing = true` peuvent être mises en avant dans les résultats de recherche ou avoir un badge spécial.
3.  **Accès à la Visite 360°** : Sur la page de détail de l'annonce, si `has_360_tour = true` et `is_premium_listing = true` (ou si l'annonce est débloquée), le composant `PannellumViewer360` affichera la visite virtuelle. Sinon, un message invitant à débloquer l'annonce ou à souscrire à un abonnement premium pourrait être affiché.
4.  **Consultation de la Galerie** : La galerie d'images ordonnée sera affichée pour une meilleure présentation visuelle de la propriété.

## Conclusion

En suivant ce guide, KeyHome pourra offrir une expérience utilisateur enrichie grâce aux visites virtuelles 360° et aux galeries d'images organisées. Le modèle de listing premium permettra également de monétiser cette fonctionnalité avancée, offrant un avantage significatif aux agents et bailleurs qui souhaitent maximiser la visibilité et l'attrait de leurs propriétés. Cette implémentation renforce la position de KeyHome comme une plateforme immobilière innovante et adaptée aux réalités du marché camerounais.

## Références

[1] Spatie Media Library: [https://spatie.be/docs/laravel-medialibrary/v11/introduction](https://spatie.be/docs/laravel-medialibrary/v11/introduction)
[2] Pannellum: [https://pannellum.org/](https://pannellum.org/)
[3] React: [https://react.dev/](https://react.dev/)
[4] Laravel: [https://laravel.com/](https://laravel.com/)
