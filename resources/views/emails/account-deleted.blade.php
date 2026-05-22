@extends('emails.layout')

@section('title', 'Votre compte a été supprimé')

@section('preheader', 'Votre compte ' . config('app.name') . ' a bien été supprimé. Toutes vos données ont été effacées conformément à notre politique.')

@section('content')

    <h1>Votre compte a été supprimé</h1>

    <p class="text">
        Bonjour <strong>{{ $userName }}</strong>,
    </p>

    <p class="text">
        Nous vous confirmons que votre compte KeyHome a bien été <strong>supprimé</strong> conformément
        à votre demande.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 24px;
        border-collapse: collapse;
        background-color: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 700; color: #991b1b;">
                    Ce qui a été supprimé :
                </p>
                <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #7f1d1d; line-height: 1.8;">
                    <li>Vos informations personnelles</li>
                    <li>Vos annonces et favoris</li>
                    <li>Votre historique de paiements et crédits</li>
                    <li>Toutes vos sessions actives</li>
                </ul>
            </td>
        </tr>
    </table>

    <p class="text" style="margin-top: 24px;">
        Vos données seront définitivement anonymisées sous <strong>30 jours</strong>.
        Pendant ce délai, vous pouvez contacter notre support si vous souhaitez annuler cette opération.
    </p>

    <p class="text" style="margin-top: 32px;">
        Nous sommes désolés de vous voir partir. Si vous changez d'avis, vous êtes toujours le bienvenu
        sur KeyHome.
    </p>

    @include('emails.partials.button', [
        'url'   => rtrim(config('app.frontend_url', config('app.url')), '/'),
        'label' => 'Visiter KeyHome',
        'width' => 200,
    ])

@endsection
