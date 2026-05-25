{{-- Internal support inbox notification — sent to KeyHome support when a visitor uses the contact form. --}}
@extends('emails.layout')

@section('title', '[Contact] ' . $contactSubject)

@section('preheader', 'Nouveau message de ' . $contactName . ' — ' . Str::limit($contactMessage, 80))

@section('content')

    <h1>Nouveau message de contact</h1>

    <p class="text" style="margin-top: 16px; color: #64748b;">
        Un visiteur vient de soumettre le formulaire de contact sur {{ config('app.name') }}.
    </p>

    <div style="margin-top: 32px; padding: 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
        <p class="text" style="margin: 0 0 8px 0;"><strong>Nom :</strong> {{ $contactName }}</p>
        <p class="text" style="margin: 0 0 8px 0;">
            <strong>Email :</strong>
            <a href="mailto:{{ $contactEmail }}" class="link">{{ $contactEmail }}</a>
        </p>
        <p class="text" style="margin: 0;"><strong>Sujet :</strong> {{ $contactSubject }}</p>
    </div>

    <h2 style="margin-top: 32px; font-size: 16px; font-weight: 700; color: #0f172a;">Message</h2>
    <div style="margin-top: 12px; padding: 20px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; white-space: pre-wrap; word-wrap: break-word;">{{ $contactMessage }}</div>

    @include('emails.partials.button', [
        'url'   => 'mailto:' . $contactEmail . '?subject=' . rawurlencode('Re: ' . $contactSubject),
        'label' => 'Répondre à ' . $contactName,
        'width' => 260,
    ])

    @if(!empty($sourceIp) || !empty($userAgent) || !empty($sourceUrl))
        <hr style="margin-top: 40px; border: none; border-top: 1px solid #e2e8f0;">
        <p class="fallback" style="margin-top: 16px; font-size: 12px; color: #94a3b8;">
            <strong>Métadonnées techniques</strong><br>
            @if(!empty($sourceIp))IP : {{ $sourceIp }}<br>@endif
            @if(!empty($userAgent))User-Agent : {{ $userAgent }}<br>@endif
            @if(!empty($sourceUrl))Page source : {{ $sourceUrl }}@endif
        </p>
    @endif

@endsection
