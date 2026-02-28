{{-- Converted from Clerk "verification code (OTP)" email template --}}
@extends('emails.layout')

@section('title', $otpCode . ' est votre code de vérification ' . config('app.name'))

@section('content')

    <h1>Code de vérification</h1>

    <p class="text" style="margin-top: 32px;">
        Entrez le code de vérification suivant lorsqu'il vous est demandé :
    </p>

    <div class="otp-box">
        <div class="otp-code">{{ $otpCode }}</div>
        <div class="otp-label">Code à usage unique — ne pas partager</div>
    </div>

    <p class="text" style="margin-top: 64px;"><strong>Vous n'avez pas fait cette demande ?</strong></p>
    <p class="text" style="margin-top: 4px;">
        Ce code a été demandé depuis <strong>{{ $requestedFrom }}</strong>
        le <strong>{{ $requestedAt }}</strong>.
        Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.
    </p>

@endsection
