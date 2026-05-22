@extends('emails.layout')

@section('title', __("emails.welcome_drip.day{$day}_subject", ['app' => config('app.name')]))

@section('preheader', __("emails.welcome_drip.day{$day}_heading") . ' — ' . config('app.name') . ' vous accompagne dans votre recherche immobilière.')

@section('content')

    <h1>{{ __("emails.welcome_drip.day{$day}_heading") }}</h1>

    <p class="text" style="margin-top: 16px;">
        {!! __("emails.welcome_drip.day{$day}_intro", ['name' => $user->firstname, 'app' => config('app.name')]) !!}
    </p>

    @if($day === 1)
        <table style="width: 100%; border-collapse: collapse; margin-top: 24px;">
            <tr>
                <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                    <span style="color: #F6475F; font-weight: 700; font-size: 16px;">1</span>
                </td>
                <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                    <strong>{{ __('emails.welcome_drip.day1_tip1') }}</strong><br>
                    <span style="color: #6b7280; font-size: 13px;">{{ __('emails.welcome_drip.day1_tip1_desc') }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                    <span style="color: #F6475F; font-weight: 700; font-size: 16px;">2</span>
                </td>
                <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                    <strong>{{ __('emails.welcome_drip.day1_tip2') }}</strong><br>
                    <span style="color: #6b7280; font-size: 13px;">{{ __('emails.welcome_drip.day1_tip2_desc') }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                    <span style="color: #F6475F; font-weight: 700; font-size: 16px;">3</span>
                </td>
                <td style="padding: 12px 0 12px 12px;">
                    <strong>{{ __('emails.welcome_drip.day1_tip3') }}</strong><br>
                    <span style="color: #6b7280; font-size: 13px;">{{ __('emails.welcome_drip.day1_tip3_desc') }}</span>
                </td>
            </tr>
        </table>
    @endif

    @include('emails.partials.button', [
        'url'   => config('app.frontend_url', config('app.url')) . '/home',
        'label' => __("emails.welcome_drip.day{$day}_cta"),
        'width' => 220,
    ])

@endsection
