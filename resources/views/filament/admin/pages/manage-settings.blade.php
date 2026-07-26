{{--
    Configuration group — UX divergence note (intentional):

    This page renders an extra "Vérification en attente" banner when a
    sensitive setting save is awaiting email-OTP confirmation. The other
    pages in the same nav group (payment-methods, force-password-change,
    manage-permissions) are bare `{{ $this->form }}` / `{{ $this->table }}`
    on purpose — they do not have a two-step save flow and therefore
    have no pending state to indicate. A polish audit (commit notes) once
    flagged the divergence; do NOT retrofit a similar banner onto the
    other pages unless they actually grow a multi-step save flow.

    The OTP gate is implemented in `App\Filament\Admin\Pages\ManageSettings::
    requestSectionOtp` + `confirmSectionOtp`; the banner here is the user-
    visible signal that the email-OTP step is required next.
--}}
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
