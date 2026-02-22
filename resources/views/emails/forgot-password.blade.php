{{-- Converted from Clerk "forgot password" email template --}}
@extends('emails.layout')

@section('title', 'Réinitialisez votre mot de passe ' . config('app.name'))

@section('content')

    <h1>Réinitialisation du mot de passe</h1>

    <p class="text" style="margin-top: 32px;">
        Nous avons reçu une demande de réinitialisation du mot de passe de votre compte
        <strong>{{ config('app.name') }}</strong>.
    </p>

    <p class="text">
        Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.
        Ce lien expirera dans <strong>60 minutes</strong>.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $resetUrl }}" class="btn">Réinitialiser le mot de passe</a>
    </div>

    <p class="fallback" style="margin-top: 24px;">
        Ou copiez et collez ce lien dans votre navigateur :<br>
        <a href="{{ $resetUrl }}" class="link">{{ $resetUrl }}</a>
    </p>

    <p class="text" style="margin-top: 64px;"><strong>Vous n'avez pas fait cette demande ?</strong></p>
    <p class="text" style="margin-top: 4px;">
        Cette demande a été effectuée depuis <strong>{{ $requestedFrom }}</strong>
        le <strong>{{ $requestedAt }}</strong>.
        Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email en toute sécurité.
    </p>

@endsection
