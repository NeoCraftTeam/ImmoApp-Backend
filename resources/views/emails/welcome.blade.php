@extends('emails.layout')

@section('title', __('emails.welcome.subject', ['app' => config('app.name')]))

@section('preheader', __('emails.welcome.preheader', ['app' => config('app.name')]))

@section('content')

    <h1>{{ __('emails.welcome.heading', ['name' => $user->lastname]) }}</h1>

    <p class="text">
        {!! __('emails.welcome.intro', ['app' => config('app.name')]) !!}
    </p>

    <p class="text" style="margin-top: 24px; font-weight: 600; color: #000;">
        {{ __('emails.welcome.what_you_can_do') }}
    </p>

    <!-- Feature list -->
    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>{{ __('emails.welcome.feature_search') }}</strong><br>
                <span style="color: #6b7280; font-size: 13px;">{{ __('emails.welcome.feature_search_desc') }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>{{ __('emails.welcome.feature_alerts') }}</strong><br>
                <span style="color: #6b7280; font-size: 13px;">{{ __('emails.welcome.feature_alerts_desc') }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px;">
                <strong>{{ __('emails.welcome.feature_favorites') }}</strong><br>
                <span style="color: #6b7280; font-size: 13px;">{{ __('emails.welcome.feature_favorites_desc') }}</span>
            </td>
        </tr>
    </table>

    @include('emails.partials.button', [
        'url'   => rtrim(config('app.frontend_url', config('app.url')), '/') . '/home',
        'label' => __('emails.welcome.cta'),
    ])

    <p class="fallback" style="margin-top: 24px;">
        {{ __('emails.welcome.help') }}
        <a href="mailto:{{ __('emails.generic.support_email') }}" class="link">{{ __('emails.generic.support_email') }}</a>.
    </p>

@endsection
