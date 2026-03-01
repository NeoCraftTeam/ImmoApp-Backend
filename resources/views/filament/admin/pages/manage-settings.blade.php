<x-filament-panels::page>
    {{ $this->form }}

    @if ($awaitingSection)
        <x-filament::section icon="heroicon-o-envelope" icon-color="warning">
            <x-slot name="heading">
                Vérification en attente
            </x-slot>
            <x-slot name="description">
                Un code de vérification a été envoyé à votre adresse email. Utilisez le bouton « Confirmer avec le code » dans la section correspondante.
            </x-slot>
        </x-filament::section>
    @endif
</x-filament-panels::page>
