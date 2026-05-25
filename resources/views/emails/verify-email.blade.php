{{-- Converted from Clerk verify email template --}}
@extends('emails.layout')

@section('title', 'Vérifiez votre adresse email - ' . config('app.name'))

@section('preheader', 'Confirmez votre adresse email pour activer votre compte — lien valable ' . ($ttlMinutes ?? 60) . ' minutes.')

@section('content')

    <h1>Vérifiez votre adresse email</h1>

    <p class="text">
        Cliquez sur le bouton ci-dessous pour vérifier votre adresse email sur {{ config('app.name') }}.
        Ce lien expirera dans <strong>{{ $ttlMinutes }} minutes</strong>.
    </p>

    @include('emails.partials.button', [
        'url'   => $magicLink,
        'label' => 'Vérifier mon adresse email',
        'width' => 260,
    ])

    <p class="fallback" style="margin: 16px 0 64px 0;">
        Si le bouton ne fonctionne pas, <a href="{{ $magicLink }}" class="link">cliquez ici</a>.
    </p>

    <p class="text" style="margin-top: 64px;"><strong>Vous n'avez pas fait cette demande ?</strong></p>
    <p class="text" style="margin-top: 4px;">
        Ce lien a été demandé depuis <strong>{{ $requestedFrom }}</strong> le <strong>{{ $requestedAt }}</strong>.
        Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.
    </p>

@endsection
