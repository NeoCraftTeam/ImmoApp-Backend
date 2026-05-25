@extends('emails.layout')

@section('title', __('emails.abandoned_search.subject', ['app' => config('app.name')]))

@section('preheader', 'Des biens correspondent encore à votre recherche — retrouvez vos annonces sauvegardées.')

@section('content')

    <h1>{{ __('emails.abandoned_search.heading') }}</h1>

    <p class="text" style="margin-top: 16px;">
        {!! __('emails.abandoned_search.intro', ['name' => $user->firstname, 'app' => config('app.name')]) !!}
    </p>

    @if($matchingAdsCount > 0)
        <div style="background-color: #f0fdf4; border-radius: 10px; padding: 20px 24px; margin: 24px 0; border: 1px solid #bbf7d0; text-align: center;">
            <span style="font-size: 32px; font-weight: 800; color: #166534;">{{ $matchingAdsCount }}</span>
            <p style="margin: 4px 0 0 0; font-size: 14px; color: #166534; font-weight: 600;">
                {{ __('emails.abandoned_search.matching', ['count' => $matchingAdsCount]) }}
            </p>
        </div>
    @endif

    @include('emails.partials.button', [
        'url'   => $searchUrl,
        'label' => __('emails.abandoned_search.cta'),
        'width' => 220,
    ])

    <p class="text" style="font-size: 13px; color: #94a3b8; margin-top: 24px;">
        {{ __('emails.abandoned_search.alert_tip') }}
    </p>

@endsection
