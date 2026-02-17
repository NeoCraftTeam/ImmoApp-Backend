<x-filament-panels::page>
    <form wire:submit="sendVerificationCode">
        {{ $this->form }}
    </form>

    @if ($awaitingCode)
        <x-filament::section icon="heroicon-o-envelope" icon-color="warning">
            <x-slot name="heading">
                Vérification en attente
            </x-slot>
            <x-slot name="description">
                Un code de vérification a été envoyé à votre adresse email. Cliquez sur « Confirmer avec le code » dans les actions ci-dessus.
            </x-slot>
        </x-filament::section>
    @endif
</x-filament-panels::page>
