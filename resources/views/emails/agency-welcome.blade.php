@extends('emails.layout')

@section('title', 'Bienvenue Agence - ' . config('app.name'))

@section('content')

    <h1>Bienvenue, {{ $user->firstname }}</h1>

    <p class="text">
        Votre compte <strong>agence</strong> KeyHome est maintenant activé.
        Vous pouvez gérer vos annonces et votre portefeuille immobilier.
    </p>

    <p class="text" style="margin-top: 24px; font-weight: 600; color: #000;">
        En tant qu'agence, vous pouvez :
    </p>

    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Publier des annonces</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Déposez et gérez vos biens immobiliers.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Gérer les réservations</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Répondez aux demandes des locataires.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px;">
                <strong>Suivre vos statistiques</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Tableaux de bord et indicateurs de performance.</span>
            </td>
        </tr>
    </table>

    @php
        $domain = config('filament.panels.agency_domain');
        $agencyUrl = $domain ? 'https://' . $domain . '/login' : (config('app.url') . '/agency/login');
    @endphp

    <div class="btn-wrapper">
        <a href="{{ $agencyUrl }}" class="btn">
            Accéder à l'espace agence
        </a>
    </div>

    <p class="fallback" style="margin-top: 24px;">
        Si vous avez des questions, contactez-nous à
        <a href="mailto:support@keyhome.app" class="link">support@keyhome.app</a>.
    </p>

@endsection
