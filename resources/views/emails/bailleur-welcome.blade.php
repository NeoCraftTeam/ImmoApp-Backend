@extends('emails.owner-layout')

@section('title', 'Votre espace bailleur est prêt — ' . config('app.name'))

@section('preheader', 'Publiez vos premières annonces, recevez des demandes de visite et gérez vos biens immobiliers — le tout depuis un seul tableau de bord.')

@section('content')

    <h1>Bienvenue, {{ $user->firstname }}</h1>

    <p class="text">
        Votre espace bailleur <strong>{{ config('app.name') }}</strong> est activé.
        Vous accédez dès maintenant à une plateforme pensée pour vous aider à louer plus vite,
        en toute confiance.
    </p>

    <p class="text" style="margin-top: 24px; font-weight: 600; color: #0f172a;">
        Ce que vous pouvez faire dès aujourd'hui :
    </p>

    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #0d9488; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Publier vos annonces</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Décrivez votre bien, ajoutez des photos et mettez-le en ligne en quelques minutes.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #0d9488; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Recevoir et gérer les demandes</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Les locataires qualifiés vous contactent directement — répondez depuis votre tableau de bord.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                <span style="color: #0d9488; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px;">
                <strong>Suivre votre portefeuille</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Annonces actives, réservations en cours et performances — tout est centralisé.</span>
            </td>
        </tr>
    </table>

    @include('emails.partials.button', [
        'url'   => rtrim(config('app.frontend_url', config('app.url')), '/') . '/owner/dashboard',
        'label' => 'Accéder à mon espace bailleur',
        'color' => '#0d9488',
        'width' => 260,
    ])

    <p class="fallback" style="margin-top: 32px; font-size: 13px;">
        Une question ? Notre équipe est disponible à
        <a href="mailto:support@keyhome.app" class="link">support@keyhome.app</a>.
    </p>

@endsection
