@extends('emails.owner-layout')

@section('title', 'Bienvenue Bailleur - ' . config('app.name'))

@section('preheader', 'Votre espace bailleur KeyHome est prêt — publiez vos premières annonces dès maintenant.')

@section('content')

    <h1>Bienvenue, {{ $user->firstname }}</h1>

    <p class="text">
        Votre compte <strong>bailleur</strong> KeyHome est maintenant activé.
        Vous pouvez publier vos annonces et gérer vos biens en location.
    </p>

    <p class="text" style="margin-top: 24px; font-weight: 600; color: #000;">
        En tant que bailleur, vous pouvez :
    </p>

    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #0d9488; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Publier vos annonces</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Mettez en ligne vos biens à louer.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #0d9488; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Recevoir des demandes</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Les locataires peuvent vous contacter directement.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                <span style="color: #0d9488; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px;">
                <strong>Gérer votre portefeuille</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Suivez vos annonces et réservations.</span>
            </td>
        </tr>
    </table>

    @include('emails.partials.button', [
        'url'   => rtrim(config('app.frontend_url', config('app.url')), '/') . '/owner/dashboard',
        'label' => 'Accéder à mon espace bailleur',
        'color' => '#0d9488',
        'width' => 260,
    ])

    <p class="fallback" style="margin-top: 24px;">
        Si vous avez des questions, contactez-nous à
        <a href="mailto:support@keyhome.app" class="link">support@keyhome.app</a>.
    </p>

@endsection
