{{-- OTP-based reset password — adapts layout for owner (teal) vs client (default) --}}
@extends($emailLayout ?? 'emails.layout')

@section('title', $otpCode . ' est votre code de réinitialisation ' . config('app.name'))

@section('preheader', 'Ce code expire dans 10 minutes — ne le communiquez à personne, même à notre équipe.')

@section('content')

    <h1>Code de réinitialisation du mot de passe</h1>

    @if(!empty($isOwner))
        <p class="text" style="margin-top: 16px; color: #0d9488; font-weight: 600;">
            Espace Bailleur — réinitialisation du mot de passe de votre compte propriétaire.
        </p>
    @endif

    <p class="text" style="margin-top: 32px;">
        Entrez le code suivant lorsqu'il vous est demandé :
    </p>

    <div class="otp-box">
        <div class="otp-code">{{ $otpCode }}</div>
        <div class="otp-label">Code de réinitialisation — valable 10 minutes</div>
    </div>

    <p class="text" style="margin-top: 64px;"><strong>Vous n'avez pas fait cette demande ?</strong></p>
    <p class="text" style="margin-top: 4px;">
        Ce code a été demandé depuis <strong>{{ $requestedFrom }}</strong>
        le <strong>{{ $requestedAt }}</strong>.
        Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.
    </p>

@endsection
