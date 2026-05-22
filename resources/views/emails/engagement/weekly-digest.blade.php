@extends('emails.layout')

@section('title', __('emails.digest.subject', ['app' => config('app.name')]))

@section('preheader', 'Votre récapitulatif hebdomadaire — découvrez les nouveaux biens et alertes disponibles cette semaine.')

@section('content')

    <h1>{{ __('emails.digest.heading') }}</h1>

    <p class="text" style="margin-top: 16px;">
        {!! __('emails.digest.intro', ['name' => $user->firstname, 'app' => config('app.name')]) !!}
    </p>

    @if(($newAdsCount ?? 0) > 0)
        <table style="width: 100%; border-collapse: collapse; margin-top: 24px; background: #f8fafc; border-radius: 12px; overflow: hidden;">
            <tr>
                <td style="padding: 24px; text-align: center;">
                    <span style="font-size: 36px; font-weight: 800; color: #F6475F;">{{ $newAdsCount }}</span>
                    <p style="margin: 4px 0 0 0; font-size: 14px; color: #475569; font-weight: 600;">
                        {{ __('emails.digest.new_ads', ['count' => $newAdsCount]) }}
                        @if(!empty($cityName))
                            {{ __('emails.digest.in_your_city') }}
                        @endif
                    </p>
                </td>
                @if(($matchingAlertsCount ?? 0) > 0)
                    <td style="padding: 24px; text-align: center; border-left: 1px solid #e2e8f0;">
                        <span style="font-size: 36px; font-weight: 800; color: #16a34a;">{{ $matchingAlertsCount }}</span>
                        <p style="margin: 4px 0 0 0; font-size: 14px; color: #475569; font-weight: 600;">
                            {{ __('emails.digest.matching_alerts', ['count' => $matchingAlertsCount]) }}
                        </p>
                    </td>
                @endif
            </tr>
        </table>
    @else
        <p class="text" style="margin-top: 16px; font-style: italic; color: #94a3b8;">
            {{ __('emails.digest.no_activity') }}
        </p>
    @endif

    @include('emails.partials.button', [
        'url'   => config('app.frontend_url', config('app.url')) . '/search',
        'label' => __('emails.digest.cta'),
        'width' => 220,
    ])

@endsection
