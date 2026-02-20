<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Dotswan\MapPicker\Fields\Map;

/**
 * Composant MapPicker optimisé pour les appareils natifs.
 * 
 * En mode natif (React Native WebView), ce composant utilise les services de géolocalisation natifs
 * pour une meilleure performance et une intégration plus fluide avec l'OS.
 */
class NativeLocationPicker extends Map
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ajouter un attribut pour identifier ce composant en JS
        $this->extraAttributes([
            'data-map-picker' => 'true',
            'data-native-location' => 'true',
        ]);

        // Ajouter une classe CSS pour les styles optimisés en mode natif
        $this->extraImgAttributes([
            'class' => 'native-map-picker',
        ]);
    }

    /**
     * Configurer le composant pour utiliser les services natifs de géolocalisation.
     */
    public function useNativeGeolocation(bool $condition = true): static
    {
        if ($condition) {
            $this->extraAttributes([
                'data-use-native-geolocation' => 'true',
            ]);
        }

        return $this;
    }
}
