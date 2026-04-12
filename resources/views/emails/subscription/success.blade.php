@extends('emails.owner-layout')

@section('title', __('emails.subscription_success.title', ['app' => config('app.name')]))

@section('content')
    <style>
        .status-badge {
            display: inline-block;
            background-color: #f0fdf4;
            color: #166534;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
            border: 1px solid #bbf7d0;
        }

        .amount-display {
            font-size: 36px;
            font-weight: 800;
            color: #0f172a;
            margin: 16px 0;
            letter-spacing: -1px;
        }

        .amount-label {
            font-size: 13px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .detail-card {
            background-color: #f8fafc;
            border-radius: 10px;
            padding: 20px 24px;
            margin: 24px 0;
            border: 1px solid #e2e8f0;
        }

        .detail-card h3 {
            margin: 0 0 12px 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
        }

        .detail-card table {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-card td {
            padding: 8px 0;
            font-size: 14px;
        }

        .detail-card td:first-child {
            color: #64748b;
        }

        .detail-card td:last-child {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }

        .detail-card tr + tr td {
            border-top: 1px solid #f1f5f9;
        }
    </style>

    <h1>{{ __('emails.subscription_success.heading') }}</h1>

    <div style="text-align: center; margin-top: 20px;">
        <span class="status-badge">{{ __('emails.subscription_success.badge') }}</span>
    </div>

    <p class="text">{{ __('emails.subscription_success.greeting', ['agency' => $agencyName]) }}</p>
    <p class="text">{{ __('emails.subscription_success.intro') }}</p>

    <div style="text-align: center; margin-top: 24px;">
        <div class="amount-label">{{ __('emails.subscription_success.amount_label') }}</div>
        <div class="amount-display">{{ $amount }} FCFA</div>
    </div>

    <div class="detail-card">
        <h3>📋 {{ __('emails.subscription_success.details_heading') }}</h3>
        <table>
            <tr>
                <td>{{ __('emails.subscription_success.plan') }}</td>
                <td>{{ $planName }}</td>
            </tr>
            <tr>
                <td>{{ __('emails.subscription_success.period') }}</td>
                <td>{{ $period }}</td>
            </tr>
            <tr>
                <td>{{ __('emails.subscription_success.valid_until') }}</td>
                <td>{{ $endsAt }}</td>
            </tr>
            <tr>
                <td>{{ __('emails.subscription_success.benefits') }}</td>
                <td>{{ __('emails.subscription_success.benefits_value') }}</td>
            </tr>
        </table>
    </div>

    <div class="btn-wrapper">
        <a href="{{ config('app.url') . '/agency' }}" class="btn">{{ __('emails.subscription_success.dashboard_cta') }}</a>
    </div>

    <p class="text">{{ __('emails.subscription_success.closing', ['app' => config('app.name')]) }}</p>
@endsection
