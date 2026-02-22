{{-- Converted from Clerk "passkey added/removed" email template --}}
@extends('emails.layout')

@section('title', 'Votre clé d\'accès ' . config('app.name') . ' a été ' . ($action === 'added' ? 'ajoutée' : 'supprimée'))

@section('content')

    <h1>Clé d'accès {{ $action === 'added' ? 'ajoutée' : 'supprimée' }}</h1>

    @if (!empty($greetingName))
        <p class="text" style="margin-top: 32px;">Bonjour {{ $greetingName }},</p>
    @endif

    <p class="text" style="margin-top: 16px;">
        Ceci est une notification automatique pour vous informer qu'une clé d'accès
        (<strong>{{ $passkeyName }}</strong>) pour votre compte
        <strong>{{ $primaryEmailAddress }}</strong>
        a été {{ $action === 'added' ? 'ajoutée' : 'supprimée' }}.
    </p>

    <p class="text" style="margin-top: 64px;"><strong>Vous n'avez pas fait cette demande ?</strong></p>
    <p class="text" style="margin-top: 4px;">
        Si vous n'êtes pas à l'origine de ce changement, veuillez contacter un administrateur.
    </p>

@endsection
