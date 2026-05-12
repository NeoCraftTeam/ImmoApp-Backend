@extends('emails.owner-layout')

@section('title', 'Votre compte a été supprimé')

@section('content')

    <h1>Votre compte a été supprimé</h1>

    <p class="text">
        Bonjour <strong>{{ $userName }}</strong>,
    </p>

    <p class="text">
        Nous vous confirmons que votre compte propriétaire KeyHome a bien été <strong>supprimé</strong>
        conformément à votre demande.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 24px;
        border-collapse: collapse;
        background-color: #f0fdfa;
        border: 1px solid #99f6e4;
        border-radius: 8px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 700; color: #134e4a;">
                    Ce qui a été supprimé :
                </p>
                <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #115e59; line-height: 1.8;">
                    <li>Vos informations personnelles</li>
                    <li>Toutes vos annonces publiées</li>
                    <li>Votre historique de paiements, crédits et abonnement</li>
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

    <div class="btn-wrapper">
        <a href="{{ rtrim(config('app.frontend_url', config('app.url')), '/') }}" class="btn">
            Visiter KeyHome
        </a>
    </div>

@endsection
