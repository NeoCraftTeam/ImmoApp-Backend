{{--
    Landlord activity report, 48 h after their last sign-in.

    Teal kit throughout, so the mail reads as the landlord panel and not as the
    visitor app. Every figure is optional: the stat, the favourites line and the
    waiting-messages line each disappear when there is nothing to report, which
    keeps the mail honest rather than padded.
--}}
@extends('emails.owner-layout')

@php
    $t = $theme ?? \App\Support\MailTheme::owner();
@endphp

@section('title', __('emails.owner_activity.hero_heading'))

@section('preheader', __('emails.owner_activity.preheader'))

@section('hero')
    @include('emails.partials.hero', [
        'heroBg' => $t['gradient'],
        'heroEyebrow' => __('emails.owner_activity.hero_eyebrow'),
        'heroText' => __('emails.owner_activity.hero_heading'),
        'heroSub' => __('emails.owner_activity.hero_sub'),
    ])
@endsection

@section('content')

    <h1>{{ __('emails.owner_activity.heading', ['name' => $user->firstname]) }}</h1>

    <p class="text">
        {!! __('emails.owner_activity.intro', ['app' => config('app.name')]) !!}
    </p>

    @if($viewCount > 0)
        @include('emails.partials.stat', [
            'value' => number_format($viewCount, 0, ',', ' '),
            'label' => $viewCount === 1
                ? __('emails.owner_activity.stat_label_one')
                : __('emails.owner_activity.stat_label'),
            'theme' => $t,
        ])
    @endif

    @if($favoriteCount > 0)
        <p class="text">{{ __('emails.owner_activity.favorites', ['count' => $favoriteCount]) }}</p>
    @endif

    @if($unansweredMessages > 0)
        <p class="text"><strong>{{ __('emails.owner_activity.messages_waiting', ['count' => $unansweredMessages]) }}</strong></p>
    @endif

    @include('emails.partials.ad-list', [
        'ads' => $adCards ?? [],
        'listTitle' => __('emails.owner_activity.top_title'),
        'theme' => $t,
    ])

    @include('emails.partials.button', [
        'url' => $panelUrl,
        'label' => __('emails.owner_activity.cta'),
        'color' => $t['accent'],
        'width' => 260,
    ])

    @include('emails.partials.divider', ['theme' => $t])

    <p class="fallback" style="margin-top:0;">
        {{ __('emails.owner_activity.tip') }}
    </p>

@endsection
