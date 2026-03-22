@extends('emails.layout')

@section('title', __('emails.refund.subject', ['amount' => number_format((float) $refund->amount, 0, ',', ' ')]))

@section('content')
    <style>
        .refund-card {
            background-color: #f0fdf4;
            border-radius: 10px;
            padding: 20px 24px;
            margin: 24px 0;
            border: 1px solid #bbf7d0;
            text-align: center;
        }

        .refund-card h3 {
            margin: 0 0 4px 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .refund-amount {
            display: inline-block;
            margin-top: 10px;
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            border-radius: 20px;
            padding: 6px 18px;
            font-size: 18px;
            font-weight: 700;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .detail-table td {
            padding: 8px 12px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-table td:first-child {
            color: #64748b;
            font-weight: 500;
        }

        .detail-table td:last-child {
            text-align: right;
            color: #0f172a;
            font-weight: 600;
        }
    </style>

    <p style="font-size: 15px; color: #334155; line-height: 1.6;">
        {{ __('emails.refund.greeting', ['name' => $refund->user->firstname ?? 'Client']) }}
    </p>

    <p style="font-size: 15px; color: #334155; line-height: 1.6;">
        {{ __('emails.refund.intro') }}
    </p>

    <div class="refund-card">
        <h3>{{ __('emails.refund.heading') }}</h3>
        <div class="refund-amount">
            {{ number_format((float) $refund->amount, 0, ',', ' ') }} XAF
        </div>
    </div>

    <table class="detail-table">
        <tr>
            <td>{{ __('emails.refund.payment_ref') }}</td>
            <td>{{ $refund->payment->transaction_id ?? '—' }}</td>
        </tr>
        <tr>
            <td>{{ __('emails.refund.type') }}</td>
            <td>{{ $refund->is_partial ? __('emails.refund.type_partial') : __('emails.refund.type_full') }}</td>
        </tr>
        <tr>
            <td>{{ __('emails.refund.reason') }}</td>
            <td>{{ $refund->reason }}</td>
        </tr>
        <tr>
            <td>{{ __('emails.refund.date') }}</td>
            <td>{{ $refund->created_at?->translatedFormat('d F Y à H:i') }}</td>
        </tr>
    </table>

    <p style="font-size: 14px; color: #64748b; line-height: 1.6; margin-top: 24px;">
        {{ __('emails.refund.processing_note') }}
    </p>

    <p style="font-size: 14px; color: #64748b; line-height: 1.6;">
        {{ __('emails.refund.contact') }}
    </p>
@endsection
