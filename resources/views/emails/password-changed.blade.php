{{-- Converted from Clerk "password changed" email template --}}
@extends('emails.layout')

@section('title', 'Votre mot de passe ' . config('app.name') . ' a été modifié')

@section('content')

    <h1>Changement de mot de passe</h1>

    @if (!empty($greetingName))
        <p class="text" style="margin-top: 32px;">Bonjour {{ $greetingName }},</p>
    @endif

    <p class="text" style="margin-top: 16px;">
        Ceci est une notification automatique pour vous informer que le mot de passe associé
        au compte <strong>{{ $primaryEmailAddress }}</strong> a été modifié.
    </p>

    <p class="text" style="margin-top: 64px;"><strong>Vous n'avez pas fait cette demande ?</strong></p>
    <p class="text" style="margin-top: 4px;">
        Si vous n'êtes pas à l'origine de ce changement, veuillez contacter un administrateur.
    </p>

@endsection
