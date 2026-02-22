{{-- Converted from Clerk "email address updated" email template --}}
@extends('emails.layout')

@section('title', 'Votre adresse email ' . config('app.name') . ' a été mise à jour')

@section('content')

    <h1>Adresse email mise à jour</h1>

    <p class="text">
        L'adresse email principale de votre compte a été mise à jour vers
        <strong>{{ $newEmailAddress }}</strong>.
    </p>

    <p class="text" style="margin-top: 64px;"><strong>Vous n'avez pas fait cette demande ?</strong></p>
    <p class="text" style="margin-top: 4px;">
        Si ce n'était pas vous, veuillez contacter un administrateur.
    </p>

    <p class="text" style="margin-top: 64px; color: #868e96;">
        Ce message est généré automatiquement. Ne répondez pas à cet email.
    </p>

@endsection
