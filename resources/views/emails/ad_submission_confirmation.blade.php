<x-mail::message>
    # Accusé de réception

    Bonjour **{{ $authorName }}**,

    Nous avons bien reçu votre annonce **"{{ $adTitle }}"**.

    Elle est actuellement **en attente de validation** par nos administrateurs. Vous recevrez une notification dès
    qu'elle sera publiée.

    Merci de votre confiance,
    L'équipe {{ config('app.name') }}
</x-mail::message>
