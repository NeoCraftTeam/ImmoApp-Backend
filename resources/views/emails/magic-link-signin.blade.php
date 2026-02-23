{{-- Converted from Clerk "signing" (magic link sign-in) email template --}}
@extends('emails.layout')

@section('title', 'Connexion à ' . config('app.name'))

@section('content')

    <h1>Connexion à {{ config('app.name') }}</h1>

    <p class="text">
        Cliquez sur le bouton ci-dessous pour vous connecter à {{ config('app.name') }}.
        Ce lien expirera dans <strong>{{ $ttlMinutes }} minutes</strong>.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $magicLink }}" class="btn">Se connecter</a>
    </div>

    <p class="fallback" style="margin: 16px 0 64px 0;">
        Si le bouton ne fonctionne pas,
        <a href="{{ $magicLink }}" class="link">cliquez ici</a>.
    </p>

    <p class="text" style="margin-top: 64px;"><strong>Vous n'avez pas fait cette demande ?</strong></p>
    <p class="text" style="margin-top: 4px;">
        Ce lien a été demandé depuis <strong>{{ $requestedFrom }}</strong>
        le <strong>{{ $requestedAt }}</strong>.
        Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.
    </p>

@endsection
