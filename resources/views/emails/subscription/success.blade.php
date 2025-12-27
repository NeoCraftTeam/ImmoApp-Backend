<x-mail::message>
    # Félicitations ! Votre abonnement est actif

    Bonjour l'équipe {{ $agencyName }},

    Nous avons le plaisir de vous confirmer l'activation de votre abonnement **{{ $planName }}** ({{ $period }}).

    Votre paiement de **{{ $amount }} FCFA** a bien été reçu.

    **Détails de votre abonnement :**
    - **Plan :** {{ $planName }}
    - **Validité jusqu'au :** {{ $endsAt }}
    - **Avantages :** Boost automatique de vos annonces et limites augmentées.

    <x-mail::button :url="config('app.url') . '/agency'">
        Accéder à mon tableau de bord
    </x-mail::button>

    Merci d'avoir choisi KeyHome pour développer votre activité !

    Cordialement,
    L'équipe {{ config('app.name') }}
</x-mail::message>
