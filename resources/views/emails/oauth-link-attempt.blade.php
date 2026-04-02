{{-- Security alert when someone attempts to link an OAuth provider to this account --}}
@extends('emails.layout')

@section('title', 'Tentative de liaison de compte ' . ucfirst($provider))

@section('content')

    <h1>Tentative de liaison de compte {{ ucfirst($provider) }}</h1>

    <p class="text">
        Bonjour {{ $userFirstName }},
    </p>

    <p class="text">
        Une tentative de liaison du compte <strong>{{ ucfirst($provider) }}</strong> à votre compte
        {{ config('app.name') }} a été détectée. Si c'est vous, ignorez cet email et complétez
        la liaison dans l'application.
    </p>

    <p class="text" style="color: #dc2626; font-weight: 600;">
        ⚠️ Si vous n'êtes pas à l'origine de cette action, sécurisez votre compte immédiatement.
    </p>

    {{-- Attempt info card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-collapse: collapse;">
        <tr>
            <td style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; border-radius: 4px; font-size: 14px; color: #000000;">
                <p style="margin: 0 0 8px 0;"><strong>Provider :</strong> {{ ucfirst($provider) }}</p>
                <p style="margin: 0 0 8px 0;"><strong>Adresse IP :</strong> {{ $ipAddress }}</p>
                <p style="margin: 0;"><strong>Heure :</strong> {{ $attemptedAt }}</p>
            </td>
        </tr>
    </table>

    <p class="text" style="margin-top: 32px;">
        Pour sécuriser votre compte, changez votre mot de passe et révoquez les sessions actives.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $secureAccountUrl }}" class="btn" style="background-color: #dc2626;">
            Sécuriser mon compte
        </a>
    </div>

    <p class="fallback">
        Si le bouton ne fonctionne pas,
        <a href="{{ $secureAccountUrl }}" class="link">cliquez ici</a>.
    </p>

    @if (!empty($supportEmail))
        <p class="text" style="margin-top: 16px;">
            Des questions ? Contactez-nous à
            <a href="mailto:{{ $supportEmail }}" class="link">{{ $supportEmail }}</a>.
        </p>
    @endif

@endsection
