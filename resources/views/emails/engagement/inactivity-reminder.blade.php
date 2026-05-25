@extends('emails.layout')

@section('title', __('emails.inactivity.subject', ['name' => $user->firstname, 'app' => config('app.name')]))

@section('preheader', 'De nouvelles annonces vous attendent — revenez découvrir les biens publiés depuis votre dernière visite.')

@section('content')

    <h1>{{ __('emails.inactivity.heading') }}</h1>

    <p class="text" style="margin-top: 16px;">
        {!! __('emails.inactivity.intro', ['days' => $daysSinceLogin, 'app' => config('app.name')]) !!}
    </p>

    @if($newAdsCount > 0)
        <div style="background-color: #f0fdf4; border-radius: 10px; padding: 20px 24px; margin: 24px 0; border: 1px solid #bbf7d0; text-align: center;">
            <span style="font-size: 32px; font-weight: 800; color: #166534;">{{ $newAdsCount }}</span>
            <p style="margin: 4px 0 0 0; font-size: 14px; color: #166534; font-weight: 600;">
                {{ __('emails.inactivity.stats', ['count' => $newAdsCount]) }}
            </p>
        </div>
    @endif

    @include('emails.partials.button', [
        'url'   => config('app.frontend_url', config('app.url')) . '/home',
        'label' => __('emails.inactivity.cta'),
        'width' => 220,
    ])

@endsection
