{{-- Converted from Clerk invitation email template --}}
@extends('emails.layout')

@section('title', 'Votre invitation sur ' . config('app.name'))

@section('content')

    <h1>Votre invitation</h1>

    <p class="text">
        @if (!empty($inviterName))
            <strong>{{ $inviterName }}</strong> vous invite à rejoindre {{ config('app.name') }}.
        @else
            Vous êtes invité à rejoindre {{ config('app.name') }}.
        @endif
    </p>

    <p class="text">
        Cette invitation expirera dans <strong>{{ $expiresInDays }} jours</strong>.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn">Accepter l'invitation</a>
    </div>

    <p class="fallback">
        Si le bouton ne fonctionne pas,
        <a href="{{ $actionUrl }}" class="link">cliquez ici</a>.
    </p>

@endsection
