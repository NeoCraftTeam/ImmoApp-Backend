@extends('emails.layout')

@section('title', $body ? Str::limit(strip_tags($body), 60) : 'Newsletter KeyHome')

@section('preheader', $subject ?? 'La newsletter KeyHome — actualités et meilleures annonces immobilières de la semaine.')

@section('content')

    @if($name)
        <h1>Bonjour {{ $name }},</h1>
    @else
        <h1>Bonjour,</h1>
    @endif

    <div class="text" style="margin-top: 16px;">
        {!! $body !!}
    </div>

    @include('emails.partials.button', [
        'url'   => config('app.frontend_url', config('app.url')),
        'label' => 'Voir les annonces sur KeyHome',
        'width' => 260,
    ])

    <p class="fallback" style="margin-top: 32px;">
        Vous recevez cet email car vous êtes abonné à la newsletter KeyHome.<br>
        <a href="{{ $unsubscribeUrl }}" class="link">Se désabonner</a>
    </p>

@endsection
