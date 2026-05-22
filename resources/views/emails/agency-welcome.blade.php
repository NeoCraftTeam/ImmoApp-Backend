@extends('emails.owner-layout')

@section('title', 'Votre espace agence est prêt — ' . config('app.name'))

@section('preheader', 'Gérez votre catalogue d\'annonces, traitez les demandes clients et suivez vos performances — tout depuis votre tableau de bord agence.')

@section('content')

    <h1>Bienvenue, {{ $user->firstname }}</h1>

    <p class="text">
        Votre espace agence <strong>{{ config('app.name') }}</strong> est activé.
        Vous disposez dès maintenant d'un outil professionnel pour développer votre activité
        immobilière et convertir plus de prospects en locataires.
    </p>

    <p class="text" style="margin-top: 24px; font-weight: 600; color: #0f172a;">
        Vos fonctionnalités disponibles immédiatement :
    </p>

    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #0d9488; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Catalogue d'annonces</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Publiez, éditez et boostez vos biens immobiliers depuis votre espace dédié.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #0d9488; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Gestion des demandes</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Suivez et traitez les demandes de visite, confirmez les réservations et communiquez avec les clients.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                <span style="color: #0d9488; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px;">
                <strong>Tableau de bord & statistiques</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Visualisez vos performances : taux de contact, vues par annonce et leads générés.</span>
            </td>
        </tr>
    </table>

    @php
        $domain = config('filament.panels.agency_domain');
        $agencyUrl = $domain ? 'https://' . $domain . '/login' : (config('app.url') . '/agency/login');
    @endphp

    @include('emails.partials.button', [
        'url'   => $agencyUrl,
        'label' => "Accéder à l'espace agence",
        'color' => '#0d9488',
        'width' => 260,
    ])

    <p class="fallback" style="margin-top: 24px;">
        Si vous avez des questions, contactez-nous à
        <a href="mailto:support@keyhome.app" class="link">support@keyhome.app</a>.
    </p>

@endsection
