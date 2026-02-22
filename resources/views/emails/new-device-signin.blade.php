{{-- Converted from Clerk "signing from new device" email template --}}
@extends('emails.layout')

@section('title', 'Nouvelle connexion à votre compte')

@section('content')

    <h1>Nouvelle connexion à votre compte</h1>

    <p class="text">
        Un nouvel appareil vient de se connecter à votre compte {{ config('app.name') }}.
        Si vous ne reconnaissez pas cet appareil, vérifiez votre compte pour toute activité non autorisée.
    </p>

    {{-- Device info card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 32px; border-collapse: collapse;">
        <tr>
            <td style="background-color: #f8f9fa; padding: 16px; border-radius: 8px; font-size: 14px; color: #000000;">
                @if (!empty($signInMethod))
                    <p style="margin: 0 0 8px 0;"><strong>Type de connexion :</strong> {{ $signInMethod }}</p>
                @endif
                <p style="margin: 0 0 8px 0;"><strong>Appareil :</strong> {{ $deviceType }} {{ $browserName }} sur {{ $operatingSystem }}</p>
                <p style="margin: 0 0 8px 0;"><strong>Localisation :</strong> {{ $location }} ({{ $ipAddress }})</p>
                <p style="margin: 0;"><strong>Heure :</strong> {{ $sessionCreatedAt }}</p>
            </td>
        </tr>
    </table>

    @if (!empty($revokeSessionUrl))
        <p class="text" style="margin-top: 32px;">
            Pour déconnecter immédiatement cet appareil, cliquez sur le bouton ci-dessous.
        </p>

        <div class="btn-wrapper">
            <a href="{{ $revokeSessionUrl }}" class="btn" style="background-color: #dc2626;">
                Déconnecter cet appareil
            </a>
        </div>

        <p class="fallback">
            Si le bouton ne fonctionne pas,
            <a href="{{ $revokeSessionUrl }}" class="link">cliquez ici</a>.
        </p>
    @endif

    @if (!empty($supportEmail))
        <p class="text" style="margin-top: 16px;">
            Si vous avez des questions, contactez-nous à
            <a href="mailto:{{ $supportEmail }}" class="link">{{ $supportEmail }}</a>
            dans les plus brefs délais.
        </p>
    @endif

@endsection
