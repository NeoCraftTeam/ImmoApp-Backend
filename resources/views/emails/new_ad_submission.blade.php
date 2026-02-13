<x-mail::message>
    # Nouvelle Annonce en attente de validation

    Bonjour,

    Une nouvelle annonce a été soumise par **{{ $authorName }}** et nécessite votre validation.

    ## Détails de l'annonceur
    - **Nom** : {{ $authorName }}
    - **Email** : {{ $authorEmail }}
    - **Rôle** : {{ $authorRole }}
    - **Type** : {{ $authorType }}

    ## Détails de l'annonce
    - **Titre** : {{ $adTitle }}
    - **Prix** : {{ $adPrice }}
    - **Type** : {{ $adType }}
    - **Quartier** : {{ $adQuarter }}

    <x-mail::button :url="$url">
        Voir l'annonce
    </x-mail::button>

    Merci,
    L'équipe {{ config('app.name') }}
</x-mail::message>
