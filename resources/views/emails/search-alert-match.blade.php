@extends('emails.layout')

@section('title', __('emails.search_alert.subject', ['app' => config('app.name')]))

@section('content')

    <h1>{{ __('emails.search_alert.heading', ['name' => $recipient->firstname]) }}</h1>

    <p class="text">
        {!! __('emails.search_alert.intro') !!}
    </p>

    <!-- Ad card -->
    <table style="width: 100%; border-collapse: collapse; margin-top: 24px; background: #f8fafc; border-radius: 12px; overflow: hidden;">
        <tr>
            <td style="padding: 24px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td>
                            <span style="display: inline-block; background: #F6475F; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.5px; text-transform: uppercase;">
                                {{ __('emails.search_alert.new_badge') }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 12px;">
                            <span style="font-size: 18px; font-weight: 800; color: #0f172a; line-height: 1.3;">
                                {{ $ad->title }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 6px;">
                            <span style="font-size: 22px; font-weight: 800; color: #F6475F;">
                                {{ $formattedPrice }}
                            </span>
                            <span style="font-size: 13px; color: #6b7280;">{{ __('emails.search_alert.per_month') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 12px;">
                            <table style="border-collapse: collapse;">
                                <tr>
                                    @if($ad->surface_area)
                                    <td style="padding-right: 16px;">
                                        <span style="font-size: 13px; color: #6b7280;">{{ __('emails.search_alert.surface') }}</span><br>
                                        <span style="font-size: 14px; font-weight: 700; color: #1e293b;">{{ $ad->surface_area }} m²</span>
                                    </td>
                                    @endif
                                    @if($ad->bedrooms)
                                    <td style="padding-right: 16px;">
                                        <span style="font-size: 13px; color: #6b7280;">{{ __('emails.search_alert.bedrooms') }}</span><br>
                                        <span style="font-size: 14px; font-weight: 700; color: #1e293b;">{{ $ad->bedrooms }}</span>
                                    </td>
                                    @endif
                                    @if($ad->bathrooms)
                                    <td>
                                        <span style="font-size: 13px; color: #6b7280;">{{ __('emails.search_alert.bathrooms') }}</span><br>
                                        <span style="font-size: 14px; font-weight: 700; color: #1e293b;">{{ $ad->bathrooms }}</span>
                                    </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @if($ad->quarter && $ad->quarter->city)
                    <tr>
                        <td style="padding-top: 8px;">
                            <span style="font-size: 13px; color: #6b7280;">
                                📍 {{ $ad->quarter->name }}, {{ $ad->quarter->city->name }}
                            </span>
                        </td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="btn-wrapper">
        <a href="{{ $adUrl }}" class="btn">
            {{ __('emails.search_alert.cta') }}
        </a>
    </div>

    <p class="text" style="margin-top: 24px; font-size: 13px; color: #94a3b8;">
        Cette notification provient de votre alerte
        @if($alert->label)
            « <strong>{{ $alert->label }}</strong> »
        @else
            de recherche
        @endif
        sur KeyHome. Vous pouvez gérer vos alertes dans vos paramètres.
    </p>

@endsection
