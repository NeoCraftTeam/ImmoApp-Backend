{{-- Converted from Clerk "forgot password" email template --}}
@extends('emails.layout')

@section('title', __('emails.forgot_password.subject', ['app' => config('app.name')]))

@section('content')

    <h1>{{ __('emails.forgot_password.heading') }}</h1>

    <p class="text" style="margin-top: 32px;">
        {!! __('emails.forgot_password.intro', ['app' => config('app.name')]) !!}
    </p>

    <p class="text">
        {!! __('emails.forgot_password.click_below') !!}
    </p>

    <div class="btn-wrapper">
        <a href="{{ $resetUrl }}" class="btn">{{ __('emails.forgot_password.cta') }}</a>
    </div>

    <p class="fallback" style="margin-top: 24px;">
        {{ __('emails.forgot_password.fallback') }}<br>
        <a href="{{ $resetUrl }}" class="link">{{ $resetUrl }}</a>
    </p>

    <p class="text" style="margin-top: 64px;"><strong>{{ __('emails.forgot_password.not_requested') }}</strong></p>
    <p class="text" style="margin-top: 4px;">
        {!! __('emails.forgot_password.requested_from', ['from' => $requestedFrom, 'at' => $requestedAt]) !!}
    </p>

@endsection
