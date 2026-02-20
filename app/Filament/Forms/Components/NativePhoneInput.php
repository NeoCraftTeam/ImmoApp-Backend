<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;

/**
 * Composant TextInput optimisé pour les numéros de téléphone en mode natif.
 *
 * En mode natif (React Native WebView), ce composant utilise le clavier téléphone natif
 * pour une meilleure UX et performance. En mode web, il se comporte comme un TextInput classique.
 */
class NativePhoneInput extends TextInput
{
    protected string $view = 'filament-forms::components.text-input';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->type('tel')
            ->tel()
            ->placeholder('+237 6XX XXX XXX');

        // En mode natif, on laisse le clavier natif gérer la saisie
        $this->extraAttributes([
            'data-native-input' => 'tel',
            'autocomplete' => 'tel',
        ]);
    }
}
