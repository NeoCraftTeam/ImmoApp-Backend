@extends('emails.layout')

@section('title', 'Nouvelle connexion depuis ' . $city . ', ' . $country)

@section('content')

    <h1>Nouvelle connexion détectée</h1>

    <p class="text">
        Bonjour <strong>{{ $userName }}</strong>,
    </p>

    <p class="text">
        Une connexion à votre compte <strong>{{ config('app.name') }}</strong> a été détectée
        depuis un <strong>emplacement géographique différent</strong> de celui habituellement utilisé.
    </p>

    {{-- Alert box --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-collapse: collapse;">
        <tr>
            <td style="
                    background-color: #fffbeb;
                    border: 1px solid #fcd34d;
                    border-left: 4px solid #f59e0b;
                    border-radius: 8px;
                    padding: 20px 24px;
                    font-size: 14px;
                    color: #1e293b;
                ">
                <p style="margin: 0 0 14px 0; font-size: 11px; font-weight: 700;
                               text-transform: uppercase; letter-spacing: 0.8px; color: #92400e;">
                    Détails de la connexion
                </p>
                <table cellpadding="0" cellspacing="0" style="width: 100%; font-size: 14px; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 5px 0; color: #64748b; width: 130px;">Localisation</td>
                        <td style="padding: 5px 0; font-weight: 600;">{{ $city }}, {{ $country }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; color: #64748b;">Adresse IP</td>
                        <td style="padding: 5px 0; font-weight: 600; font-family: monospace;">{{ $ipAddress }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; color: #64748b;">Appareil</td>
                        <td style="padding: 5px 0;">{{ $device }} — {{ $browser }} — {{ $operatingSystem }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; color: #64748b;">Date et heure</td>
                        <td style="padding: 5px 0;">{{ $loginAt }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="text" style="margin-top: 24px;">
        <strong>C'est vous ?</strong> Vous n'avez rien à faire, votre compte est en sécurité.
    </p>

    <p class="text">
        <strong>Ce n'est pas vous ?</strong> Sécurisez immédiatement votre compte en cliquant
        sur le bouton ci-dessous.
    </p>

    @if(!empty($secureAccountUrl))
        <div class="btn-wrapper">
            <a href="{{ $secureAccountUrl }}" class="btn" style="background-color: #dc2626;">
                Ce n'est pas moi — Sécuriser mon compte
            </a>
        </div>

        <p class="fallback">
            Si le bouton ne fonctionne pas,
            <a href="{{ $secureAccountUrl }}" class="link">cliquez ici</a>.
        </p>
    @endif

    <p class="text" style="margin-top: 32px; font-size: 12px; color: #64748b;">
        Cet email a été envoyé automatiquement pour protéger votre compte.
        @if(!empty($supportEmail))
            Pour toute question, contactez-nous à
            <a href="mailto:{{ $supportEmail }}" class="link">{{ $supportEmail }}</a>.
        @endif
    </p>

@endsection
