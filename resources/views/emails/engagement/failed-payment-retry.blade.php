@extends('emails.layout')

@section('title', __('emails.failed_payment.subject', ['app' => config('app.name')]))

@section('preheader', 'Votre paiement n\'a pas abouti — relancez la transaction en quelques secondes.')

@section('content')

    <h1>{{ __('emails.failed_payment.heading') }}</h1>

    <p class="text" style="margin-top: 16px;">
        {!! __('emails.failed_payment.intro', ['name' => $user->firstname, 'amount' => $amount, 'type' => $paymentType]) !!}
    </p>

    <p class="text">
        {{ __('emails.failed_payment.reason') }}
    </p>

    <div style="background-color: #fef2f2; border-radius: 10px; padding: 20px 24px; margin: 24px 0; border: 1px solid #fecaca; text-align: center;">
        <span style="font-size: 24px; font-weight: 800; color: #dc2626;">{{ $amount }} XAF</span>
        <p style="margin: 4px 0 0 0; font-size: 13px; color: #991b1b; font-weight: 600;">
            {{ $paymentType }}
        </p>
    </div>

    @include('emails.partials.button', [
        'url'   => config('app.frontend_url', config('app.url')) . '/pricing',
        'label' => __('emails.failed_payment.cta'),
        'width' => 220,
    ])

    <p class="fallback" style="margin-top: 24px;">
        {{ __('emails.failed_payment.help') }}
        <a href="mailto:{{ __('emails.generic.support_email') }}" class="link">{{ __('emails.generic.support_email') }}</a>.
    </p>

@endsection
