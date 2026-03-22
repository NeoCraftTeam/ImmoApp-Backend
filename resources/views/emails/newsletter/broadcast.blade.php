@extends('emails.layout')

@section('title', $body ? Str::limit(strip_tags($body), 60) : 'Newsletter KeyHome')

@section('content')

    @if($name)
        <h1>Bonjour {{ $name }},</h1>
    @else
        <h1>Bonjour,</h1>
    @endif

    <div class="text" style="margin-top: 16px;">
        {!! $body !!}
    </div>

    <div class="btn-wrapper" style="margin-top: 32px;">
        <a href="{{ config('app.frontend_url', config('app.url')) }}" class="btn">
            Voir les annonces sur KeyHome
        </a>
    </div>

    <p class="fallback" style="margin-top: 32px;">
        Vous recevez cet email car vous êtes abonné à la newsletter KeyHome.<br>
        <a href="{{ $unsubscribeUrl }}" class="link">Se désabonner</a>
    </p>

@endsection
