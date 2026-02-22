@extends('emails.layout')

@section('title', 'Bienvenue sur ' . config('app.name') . ' !')

@section('content')

    <h1>Bienvenue, {{ $user->firstname }} 👋</h1>

    <p class="text">
        Votre compte <strong>KeyHome</strong> est maintenant activé. Vous faites officiellement partie
        de la communauté !
    </p>

    <p class="text" style="margin-top: 24px; font-weight: 600; color: #000;">
        Voici ce que vous pouvez faire dès maintenant :
    </p>

    <!-- Feature list -->
    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Rechercher intelligemment</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Filtres avancés, recherche par carte et par quartier.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Créer des alertes</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Soyez notifié dès qu'un bien correspond à vos critères.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px;">
                <strong>Gérer vos favoris</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Sauvegardez les annonces qui vous intéressent.</span>
            </td>
        </tr>
    </table>

    <div class="btn-wrapper">
        <a href="{{ config('app.frontend_url', config('app.url')) }}/home" class="btn">
            Accéder à mon espace
        </a>
    </div>

    <p class="fallback" style="margin-top: 24px;">
        Si vous avez des questions, notre équipe est disponible à
        <a href="mailto:support@neocraft.dev" class="link">support@neocraft.dev</a>.
    </p>

@endsection
