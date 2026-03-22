{{-- Converted from Clerk "verification code (OTP)" email template --}}
@extends('emails.layout')

@section('title', __('emails.verification_code.subject', ['code' => $otpCode, 'app' => config('app.name')]))

@section('content')

    <h1>{{ __('emails.verification_code.heading') }}</h1>

    <p class="text" style="margin-top: 32px;">
        {{ __('emails.verification_code.enter_code') }}
    </p>

    <div class="otp-box">
        <div class="otp-code">{{ $otpCode }}</div>
        <div class="otp-label">{{ __('emails.verification_code.otp_label') }}</div>
    </div>

    <p class="text" style="margin-top: 64px;"><strong>{{ __('emails.verification_code.not_requested') }}</strong></p>
    <p class="text" style="margin-top: 4px;">
        {!! __('emails.verification_code.requested_from', ['from' => $requestedFrom, 'at' => $requestedAt]) !!}
    </p>

@endsection
