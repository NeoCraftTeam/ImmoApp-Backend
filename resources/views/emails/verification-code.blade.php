{{-- OTP verification code email — adapts layout for owner (teal) vs client (default) --}}
@extends($emailLayout ?? 'emails.layout')

@section('title', __('emails.verification_code.subject', ['code' => $otpCode, 'app' => config('app.name')]))

@section('preheader', 'Votre code expire dans 10 minutes — ne le partagez jamais, même avec notre équipe.')

@section('content')

    <h1>{{ __('emails.verification_code.heading') }}</h1>

    @if(!empty($isOwner))
        <p class="text" style="margin-top: 16px; color: #0d9488; font-weight: 600;">
            Espace Bailleur — vérification de votre compte propriétaire.
        </p>
    @endif

    <p class="text" style="margin-top: 32px;">
        {{ __('emails.verification_code.enter_code') }}
    </p>

    <div class="otp-box">
        <div class="otp-code">{{ $otpCode }}</div>
        <div class="otp-label">{{ __('emails.verification_code.otp_label') }}</div>
    </div>

    <p class="text" style="margin-top: 64px;"><strong>{{ __('emails.verification_code.not_requested') }}</strong></p>
    <p class="text" style="margin-top: 4px;">
        {!! __('emails.verification_code.requested_from', ['from' => $requestedFrom, 'at' => $requestedAt]) !!}
    </p>

@endsection
