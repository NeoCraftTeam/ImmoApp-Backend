<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

/**
 * Composant SpatieMediaLibraryFileUpload optimisé pour les appareils natifs.
 *
 * En mode natif (React Native WebView), ce composant intercepte le clic sur le
 * sélecteur de fichiers et délègue au bridge natif (KeyHomeBridge.pickImage /
 * takePhoto) pour une UX native (galerie ou caméra) au lieu d'un <input file>.
 *
 * Le JS injecté dans la WebView (App.js INJECTED_JS) gère côté browser :
 *   - Interception des clics sur [data-native-image="true"]
 *   - Réception de IMAGE_SELECTED / PHOTO_TAKEN depuis le natif
 *   - Injection du blob dans FilePond via DataTransfer
 */
class NativeImageUpload extends SpatieMediaLibraryFileUpload
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->image()
            ->imagePreviewHeight('80')
            ->extraAttributes([
                'data-native-image' => 'true',
            ]);
    }

    /**
     * Permettre aussi la capture directe (caméra) depuis le natif.
     */
    public function withCamera(bool $condition = true): static
    {
        if ($condition) {
            $this->extraAttributes([
                'data-native-image' => 'true',
                'data-native-image-camera' => 'true',
            ]);
        }

        return $this;
    }
}
