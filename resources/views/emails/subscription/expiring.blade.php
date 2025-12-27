<x-mail::message>
    # Votre abonnement expire bientôt

    Bonjour l'équipe {{ $agencyName }},

    Nous voulions vous informer que votre abonnement au plan **{{ $planName }}** expirera dans **{{ $days }} jour(s)**,
    le {{ $endsAt }}.

    Pour éviter toute interruption de vos services et garder vos annonces boostées, nous vous invitons à renouveler
    votre abonnement dès maintenant.

    <x-mail::button :url="config('app.url') . '/agency'">
        Renouveler mon abonnement
    </x-mail::button>

    Si vous avez des questions, n'hésitez pas à répondre à cet email.

    Merci de votre confiance,
    L'équipe {{ config('app.name') }}
</x-mail::message>
