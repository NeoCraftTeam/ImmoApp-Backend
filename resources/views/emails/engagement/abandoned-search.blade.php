{{--
    Client win-back, 48 h after browsing stopped.

    Built on the shared kit rather than inline markup: hero for the question,
    ad-list to reprint the exact flats the reader was looking at, stat for what
    they missed since. The old version was a heading, a paragraph and a green
    box hard-coded in the landlord's colour.
--}}
@extends('emails.layout')

@php
    $t = $theme ?? \App\Support\MailTheme::client();
@endphp

@section('title', __('emails.abandoned_search.subject'))

@section('preheader', __('emails.abandoned_search.preheader'))

@section('hero')
    @include('emails.partials.hero', [
        'heroBg' => $t['gradient'],
        'heroEyebrow' => __('emails.abandoned_search.hero_eyebrow'),
        'heroText' => __('emails.abandoned_search.hero_heading'),
        'heroSub' => __('emails.abandoned_search.hero_sub'),
    ])
@endsection

@section('content')

    <h1>{{ __('emails.abandoned_search.heading', ['name' => $user->firstname]) }}</h1>

    <p class="text">
        {!! __('emails.abandoned_search.intro', ['app' => config('app.name')]) !!}
    </p>

    {{-- The flats they actually opened. Nothing prints if none could be resolved. --}}
    @include('emails.partials.ad-list', [
        'ads' => $adCards ?? [],
        'listTitle' => __('emails.abandoned_search.seen_title'),
        'theme' => $t,
    ])

    @if(!empty($adCards))
        <p class="fallback">{{ __('emails.abandoned_search.seen_note') }}</p>
    @endif

    @if($matchingAdsCount > 0)
        @include('emails.partials.stat', [
            'value' => number_format($matchingAdsCount, 0, ',', ' '),
            'label' => $matchingAdsCount === 1
                ? __('emails.abandoned_search.stat_label_one')
                : __('emails.abandoned_search.stat_label'),
            'theme' => $t,
        ])
    @endif

    @include('emails.partials.button', [
        'url' => $searchUrl,
        'label' => __('emails.abandoned_search.cta'),
        'color' => $t['accent'],
        'width' => 240,
    ])

    @include('emails.partials.divider', ['theme' => $t])

    <p class="fallback" style="margin-top:0;">
        {{ __('emails.abandoned_search.alert_tip') }}
    </p>

    <p class="fallback">
        {{ __('emails.abandoned_search.found_it') }}
        @isset($preferencesUrl)
            <a href="{{ $preferencesUrl }}" class="link" style="color:{{ $t['link'] }};">{{ __('emails.layout.manage_preferences') }}</a>
        @endisset
    </p>

@endsection
